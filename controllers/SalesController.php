<?php

namespace frontend\controllers;

use Yii;
use app\models\Sales;
use app\models\SalesSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\base\ErrorException;
use yii\filters\VerbFilter;
use app\models\SalesTransaction;
use app\models\Vehicle;
use app\models\Journal;
use app\models\AccountingPeriod;
use app\models\SalesTransactionType;


/**
 * SalesController implements the CRUD actions for Sales model.
 */
class SalesController extends Controller
{
    public $defaultAction = 'home';

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Sales models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SalesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['sales_id'=>SORT_DESC],]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Sales model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('view', [
                'model' => $this->findModel($id),
            ]);
        } else {
            return $this->render('view', [
                'model' => $this->findModel($id),
            ]);
        }
    }

    /**
     * Creates a new Sales model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new Sales();
        $model->loadDefaultValues();
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');
        $model->sales_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->createOpeningInvoice($model);
            $this->makeNewSalesProcedures($model);
            return $this->redirect(['update', 'id' => $model->sales_id]);
        }elseif (Yii::$app->request->isAjax) {
            return $this->renderAjax('_form', [
                        'model' => $model
            ]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Sales model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = $this->findModel($id);
        $model->update_user_id = $user_id;
        $model->update_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['update', 'id' => $model->sales_id]);
        }elseif (Yii::$app->request->isAjax) {
            return $this->renderAjax('_form', [
                        'model' => $model
            ]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Sales model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
    * makeSalesProcedures
    * 1. Create Batch -> Create Journal -> Populate Journal Posting -> Reserve Vehicle
    */
    public function makeNewSalesProcedures($model)
    {
        // Yii::$app->runAction('new_controller/new_action', $params);
        $transaction = Yii::$app->db->beginTransaction();
        try{
                $tranx = new SalesTransaction();
                // Create batch
                if($batch_id = $tranx->createBatch('SALES')){
                    $params = [
                        'batch_id'=>$batch_id,
                        'journal_type_id'=>Journal::SALES_JOURNAL_TYPE_ID
                        ];

                    // Create journal
                    if($journal_id = $tranx->createJournal($params)){
                        // Get related invoice transaction accounts
                        $accounts = $tranx->getDebitCreditAccounts(SalesTransactionType::INVOICE_TRNX_TYPE);
                        // Get current accounting period
                        $accounting_period = AccountingPeriod::findOne([
                            'status'=>1,
                            'record_status'=>'active']);

                        $params_debit = [
                            'journal_id' => $journal_id,
                            'unit_amount' => $model->final_sales_amount,
                            'account_id' => $accounts->debit_account_id,
                            'accounting_period_id' => $accounting_period->accounting_period_id,
                            'create_user_id' => $model->create_user_id,
                            ];
                        $params_credit = [
                            'journal_id' => $journal_id,
                            'unit_amount' => ($model->final_sales_amount * -1),
                            'account_id' => $accounts->credit_account_id,
                            'accounting_period_id' => $accounting_period->accounting_period_id,
                            'create_user_id' => $model->create_user_id,
                            ];
                        // Populate journal
                        if($tranx->populateJournal($params_debit) && $tranx->populateJournal($params_credit) ){
                                // Reserve vehicle
                                $params_vehicle = [
                                    'vehicle_id' => $model->vehicle_id,
                                    'stock_status_id' => Vehicle::RESERVED_STATUS,
                                    'update_user_id' => $model->create_user_id,
                                    'update_date' => date('Y-m-d'),
                                    'record_status' => Vehicle::ACTIVE
                                    ];
                                $tranx->vehicleStockStatus($params_vehicle);
                                // print_r($params_vehicle);
                                // die();
                        } else {
                          print_r('Debit Amount: ' . $params_debit . '<br>Credit Amount: ' . $params_credit );
                          die();
                        }
                    }
                }
                $transaction->commit();
            }catch(\Exception $e){
                $transaction->rollBack();
                throw $e;
            }
    }

    /**
    * handle excess payments
    */
    public function actionRepay($id)
    {
        $model = $this->findModel($id);
        $transaction = Yii::$app->db->beginTransaction();
        try{
                $user_id = \Yii::$app->user->identity->id;
                $tranx = new SalesTransaction();
                // Create batch
                if($batch_id = $tranx->createBatch('CRNOTE')){
                    $params = [
                        'batch_id'=>$batch_id,
                        'journal_type_id'=>Journal::REFUND_JOURNAL_TYPE
                        ];

                    // Create journal
                    if($journal_id = $tranx->createJournal($params)){
                        // Get related invoice transaction accounts
                        $accounts = $tranx->getDebitCreditAccounts(SalesTransactionType::CRNOTE_TRNX_TYPE);
                        // Get current accounting period
                        $accounting_period = AccountingPeriod::findOne([
                            'status'=>1,
                            'record_status'=>'active']);

                        $params_debit = [
                            'journal_id' => $journal_id,
                            'unit_amount' => abs($model->balance),
                            'account_id' => $accounts->debit_account_id,
                            'accounting_period_id' => $accounting_period->accounting_period_id,
                            'create_user_id' => $model->create_user_id,
                            ];
                        $params_credit = [
                            'journal_id' => $journal_id,
                            'unit_amount' => abs($model->balance) * -1,
                            'account_id' => $accounts->credit_account_id,
                            'accounting_period_id' => $accounting_period->accounting_period_id,
                            'create_user_id' => $model->create_user_id,
                            ];
                        // Populate journal
                        if($tranx->populateJournal($params_debit) && $tranx->populateJournal($params_credit) ){
                            //TODO: Create a Sales Transaction
                            // Create a Refund transactions
                            $params_refund = [
                              'sales_id' => $model->sales_id,
                              'paid_amount' => abs($model->balance),
                              'user_id' => $user_id
                            ];
                            $tranx->createRefund($params_refund);

                            // Recalculate Balances
                            $model->paid_amount = (abs($model->paid_amount) - abs($model->balance));
                            $model->balance = 0.00;
                            $model->notes = date('Y-m-d') . ' : P ' . abs($model->balance) . ' Refund <br/> ' . $model->notes;
                            $model->update_user_id = $user_id;
                            $model->update_date = date('Y-m-d');
                            $model->save();

                            $transaction->commit();
                            return $this->redirect(['update', 'id' => $id]);

                        }else{ $transaction->rollBack(); }
                    }else{ $transaction->rollBack(); }
                }
            }catch(\Exception $e){
                $transaction->rollBack();
                throw $e;
            }
            return $this->redirect(['update', 'id' => $id]);
    }
    /**
    * Create an opening invoice on the SalesTransaction
    * @throws NotFoundHttpException
    */
    public function createOpeningInvoice($model)
    {
        if($model !== null){
            try{
              $sales_transaction = new SalesTransaction();
              $sales_transaction->sales_id = $model->create_user_id;
              $sales_transaction->transaction_date = $model->sales_date;
              $sales_transaction->sales_transaction_type_id = SalesTransactionType::INVOICE_TRNX_TYPE;
              $sales_transaction->transaction_amount = $model->final_sales_amount;
              $sales_transaction->total_amount = $model->final_sales_amount;
              $sales_transaction->record_status = Sales::ACTIVE;
              $sales_transaction->create_user_id = $model->create_user_id;
              $sales_transaction->create_date = date('Y-m-d');
              $sales_transaction->save();
            } catch (ErrorException $ex){
              Yii::warning("Sales Transaction input error on Sales, %id <br> $ex");
              // die();
            }
            // print_r($sales_transaction); die();
        }else{
            throw new NotFoundHttpException('The requested page does not exist.');
            die();
        }
    }

    public function actionReturn($id)
    {
        $model = $this->findModel($id);
        if(!Sales::reverseSales($model)){
            throw new \yii\web\NotFoundHttpException;
        }
        return $this->redirect(['update', 'id'=>$id]);
    }

    public function actionReport($id = null)
    {
        $htmlContent = '';

        if($id){
            $model = $this->findModel($id);
            // Set page headers
            $htmlContent .= $this->renderPartial('@app/views/report/_header');
            // get your HTML raw content without any layouts or scripts
            $htmlContent .= $this->renderPartial('_report-view',[
                    'model' => $model,
                ]);

            $htmlContent .= $this->renderPartial('@app/views/sales-transaction/_index',[
                    'dataProvider' => new \yii\data\ActiveDataProvider([
                        'query' => $model->getSalesTransactions()->where(['record_status' => Sales::ACTIVE]),
                        'pagination' => false
                        ]),
                ]);
        } 

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Sales| |F-21 Report|'],
            'SetFooter'=>['Sales Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'title' => 'Sales Report No.' . $id,
                'subject' => 'Fleet 21 Sales Report No.' . $id,
                'output' => 'fleet21_sales_report.pdf',
                'keywords' => 'skymouse fleet-21 sales report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    public function actionSalesCard(array $params = array())
    {
        if(isset($params['start_date'])){
            $start_date = $params['start_date'];
            $end_date = isset($params['end_date']) ? $params['end_date'] : $start_date;

             $sales_models = Sales::find()
                ->where(['between', 'sales_date', $start_date, $end_date])
                ->select('sales_id')
                ->all();

             foreach ($sales_models as $model) {
             
                $model = $this->findModel($model->sales_id);
                // Set page headers
                $htmlContent .= $this->renderPartial('@app/views/report/_header');
                // get your HTML raw content without any layouts or scripts
                $htmlContent .= $this->renderPartial('_report-view',[
                        'model' => $model,
                    ]);

                $htmlContent .= $this->renderPartial('@app/views/sales-transaction/_index',[
                        'dataProvider' => new \yii\data\ActiveDataProvider([
                            'query' => $model->getSalesTransactions()->where(['record_status' => Sales::ACTIVE]),
                            'pagination' => false
                            ]),
                    ]);

                $htmlContent .= "<pagebreak />";

            }

        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        // $htmlContent = '';

        // if($id){
        //     $model = $this->findModel($id);
        //     // Set page headers
        //     $htmlContent .= $this->renderPartial('@app/views/report/_header');
        //     // get your HTML raw content without any layouts or scripts
        //     $htmlContent .= $this->renderPartial('_report-view',[
        //             'model' => $model,
        //         ]);

        //     $htmlContent .= $this->renderPartial('@app/views/sales-transaction/_index',[
        //             'dataProvider' => new \yii\data\ActiveDataProvider([
        //                 'query' => $model->getSalesTransactions()->where(['record_status' => Sales::ACTIVE]),
        //                 'pagination' => false
        //                 ]),
        //         ]);
        // } 

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Sales| |F-21 Report|'],
            'SetFooter'=>['Sales Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'title' => 'Sales Report No.' . $id,
                'subject' => 'Fleet 21 Sales Report No.' . $id,
                'output' => 'fleet21_sales_report.pdf',
                'keywords' => 'skymouse fleet-21 sales report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    
    }

    public function actionListReport($filter = null)
    {
        $query = Sales::find();
        $title = 'Sales: ' . ucfirst($filter);
        
        switch($filter)
        {
            case 'settled':
                $query = $query->where(['=', 'balance', 0]);
                break;
            case 'reserved':
                $query = $query->where(['>', 'balance', 0]);
                $query = $query->joinWith('vehicle')
                    ->andWhere(['stock_status_id'=>Vehicle::RESERVED_STATUS]);
                break;
            case 'debtors':
                $query = $query->where(['>', 'balance', 0]);
                $query = $query->joinWith('vehicle')
                    ->andWhere(['stock_status_id'=>Vehicle::SOLD_STATUS]);
                break;
            case 'liability':
                $query = $query->where(['<', 'balance', 0]);
            default:
                break;
        }

        $dataProvider = new \yii\data\ActiveDataProvider([
              'query' => $query,
              'pagination' => [
                  'pageSize' => 80,
              ],
          ]);

        $htmlContent = $this->renderPartial('_index-report', [
              // 'searchModel' => $searchModel,
              'dataProvider' => $dataProvider,
              'title' => $title,
          ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Sales| |Fleet-21 Report|'],
            'SetFooter'=>['Sales Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'orientation' => 'ORIENT_LANDSCAPE',
                'title' => 'Sales Report No.' . $id,
                'subject' => 'Fleet 21 Sales Report No.' . $id,
                'output' => 'fleet21_sales_report.pdf',
                'keywords' => 'skymouse fleet-21 sales report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }


    public function actionPeriodicalReport(array $params = array())
    {
        $title = 'Sales Report: ';
        $query = Sales::find();

        if(isset($params['start_date'])){
            $start_date = $params['start_date'];
            $end_date = isset($params['end_date']) ? $params['end_date'] : $start_date;

            $query = $query->where(['between', 'sales_date', $start_date, $end_date]);

            $title .= $start_date . ' - ' . $end_date;
        }


        $dataProvider = new \yii\data\ActiveDataProvider([
              'query' => $query,
              'pagination' => [
                  'pageSize' => 80,
              ],
          ]);

        $htmlContent = $this->renderPartial('_index-report', [
              // 'searchModel' => $searchModel,
              'dataProvider' => $dataProvider,
              'title' => $title,
          ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Sales| |Fleet-21 Report|'],
            'SetFooter'=>['Sales Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'orientation' => 'ORIENT_LANDSCAPE',
                'title' => 'Sales Report No.' . $id,
                'subject' => 'Fleet 21 Sales Report No.' . $id,
                'output' => 'fleet21_sales_report.pdf',
                'keywords' => 'skymouse fleet-21 sales report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    public function actionHome()
    {
        return $this->render('home');
    }

    /**
     * Finds the Sales model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Sales the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Sales::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public static function salesSettled()
    {
        $all_settled_sales = Sales::find()
            ->select( 'sales_id' )
            ->Where(['balance' => '0.00000'])
            ->count();
            
       $sales_settled_today = Sales::find()
            ->select( 'sales_id' )
            ->Where(['balance' => '0'])
            ->AndWhere(['sales_date' => 'CURDATE()'])
            ->count();
            
       return array('all_settled_sales' => $all_settled_sales, 'sales_settled_today' => $sales_settled_today);
            
    }
}
