<?php

namespace frontend\controllers;

use Yii;
use app\models\Sales;
use app\models\Batch;
use app\models\Journal;
use app\models\SalesTransaction;
use app\models\AccountingPeriod;
use app\models\SalesTransactionType;
use app\models\SalesTransactionSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * SalesTransactionController implements the CRUD actions for SalesTransaction model.
 */
class SalesTransactionController extends Controller
{
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
     * Lists all SalesTransaction models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SalesTransactionSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single SalesTransaction model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->renderAjax('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new SalesTransaction model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($relation_id)
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new SalesTransaction();
        $model->loadDefaultValues();
        $model->sales_id = $relation_id;
        $model->transaction_date = date('Y-m-d');
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) ) {
            $model->save();

            if($this->createGLPost($model->sales_transaction_id)){
                return $this->redirect(['sales/update', 'id' => $model->sales_id]);
            }

        } else {
            return $this->renderAjax('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing SalesTransaction model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->sales_transaction_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing SalesTransaction model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /***
    * Record transaction in GL
    * 1. Create Batch -> Create Journal -> Populate Journal Posting
    **/

    public function createGLPost($id)
    {
        if(($model = $this->findModel($id)) !== null)
        {
            try{
                $user_id = \Yii::$app->user->identity->id;
                // journal_id [receipt = 1 | return 3]
                $journal_type_id = $model->sales_transaction_type_id;
                $batch_prefix = $model->sales_transaction_type_id == 1 ? 'RECEIPT' : 'RETURN';

                // create batch
                // $batch_id = $model->createBatch('RECEIPT');
                $batch_id = $model->createBatch($batch_prefix);
                
                // create journal
                $params = ['batch_id'=>$batch_id,'journal_type_id'=>$journal_type_id];
                $journal_id = $model->createJournal($params);
                $accounts = $model->getDebitCreditAccounts($journal_type_id);

                // Get current accounting period
                $accounting_period = AccountingPeriod::findOne([
                    'status'=>1,
                    'record_status'=>'active']);

                $params_debit = [
                    'journal_id' => $journal_id,
                    'unit_amount' => $model->total_amount,
                    'account_id' => $accounts->debit_account_id,
                    'accounting_period_id' => $accounting_period->accounting_period_id,
                    'create_user_id' => $model->create_user_id,
                    ];

                $params_credit = [
                    'journal_id' => $journal_id,
                    'unit_amount' => ($model->total_amount * -1),
                    'account_id' => $accounts->credit_account_id,
                    'accounting_period_id' => $accounting_period->accounting_period_id,
                    'create_user_id' => $model->create_user_id,
                    ];

                $model->populateJournal($params_debit);
                $model->populateJournal($params_credit);

                return;

            }catch(\Exception $e){
                // throw $e;
                throw new ErrorException('Operation failed to complete.');
            } 
        }
    }

    /***
    * Void a transaction
    */
    public function actionVoid($id)
    {
        $model = $this->findModel($id);
        if($model !== null ){
            $model->void = true;
            $model->record_status = 'inactive';
            $model->update_user_id = \Yii::$app->user->identity->id;
            $model->update_date = date('Y-m-d');                
            $model->update();

            // Pass transaction to Accounting Module
            $this->makeVoidProcedures($model);
            return $this->redirect(['sales/update', 'id' => $model->sales_id]);
        }else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

   /**
    * makeSalesProcedures
    * 1. Create Batch -> Create Journal -> Populate Journal Posting -> Reserve Vehicle
    */
    public function makeVoidProcedures($model)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try{
        
                $tranx = new SalesTransaction();
                // Create batch
                if($batch_id = $tranx->createBatch('RETURN')){
                    $params = [
                        'batch_id'=>$batch_id, 
                        'journal_type_id'=>Journal::RETURN_JOURNAL_TYPE
                        ];

                    // Create journal
                    if($journal_id = $tranx->createJournal($params)){
                        // Get related invoice transaction accounts
                        $accounts = $tranx->getDebitCreditAccounts(SalesTransactionType::RETURN_TRNX_TYPE);
                        // Get current accounting period
                        $accounting_period = AccountingPeriod::findOne([
                            'status'=>1,
                            'record_status'=>'active']);
                        
                        $params_debit = [
                            'journal_id' => $journal_id,
                            'unit_amount' => $model->total_amount,
                            'account_id' => $accounts->debit_account_id,
                            'accounting_period_id' => $accounting_period->accounting_period_id,
                            'create_user_id' => $model->create_user_id,
                            ];
                        $params_credit = [
                            'journal_id' => $journal_id,
                            'unit_amount' => ($model->total_amount * -1),
                            'account_id' => $accounts->credit_account_id,
                            'accounting_period_id' => $accounting_period->accounting_period_id,
                            'create_user_id' => $model->create_user_id,
                            ];
                        // Populate journal
                        if($tranx->populateJournal($params_debit) && $tranx->populateJournal($params_credit) ){
                            $transaction->commit();
                        }else{ $transaction->rollBack(); }
                    }else{ $transaction->rollBack(); }
                } 
            }catch(\Exception $e){
                $transaction->rollBack();
                throw $e;
            }  
    }

    public function actionReport($id)
    {
        $model = $this->findModel($id);
        $htmlContent = '';

        // $htmlContent .= '<pagebreak sheet-size="A5-L" />';
        // Set page headers
        $htmlContent .= $this->renderPartial('@app/views/report/_header');
        // get your HTML raw content without any layouts or scripts
        $htmlContent .= $this->renderPartial('_report-view',[
                'model' => $model,
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Sales Transaction| |Fleet-21 Report|'], 
            'SetFooter'=>['Skymouse Fleet-21 Sales Transaction Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'title' => 'Sales Transaction Report No.' . $id,
                'subject' => 'Fleet 21: Sales Transaction Report No.' . $id,
                'output' => 'fleet21_sales_trnx_report.pdf',
                'keywords' => 'skymouse fleet-21 sales report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    public function actionTrnxReport(array $params = array())
    {

        $htmlContent = '';

        if(isset($params['start_date'])){
            $start_date = $params['start_date'];
            $end_date = isset($params['end_date']) ? $params['end_date'] : $start_date;

            $trnx_type = SalesTransaction::find()
                ->distinct('sales_transaction_type_id')
                ->where(['between', 'transaction_date', $start_date, $end_date])
                ->select('sales_transaction_type_id')
                ->all();

            foreach ($trnx_type as $key) {
                $query = SalesTransaction::find()
                    ->where(['sales_transaction_type_id' => $key->sales_transaction_type_id])
                    ->andWhere(['between', 'transaction_date', $start_date, $end_date])
                    ->all();

                $title = 'Sales Transaction Report: ';
                $title .= $start_date . ' - ' . $end_date;
                $title .= "\n" . SalesTransactionType::find()
                    ->where(['sales_transaction_type_id' => $key->sales_transaction_type_id])
                    ->one()->sales_transaction_type;

                // $query = (new \yii\db\Query())
                //     ->select('*')
                //     ->from('sales_transaction')
                //     ->limit(10)
                //     ->all();
                    // ->where([
                    //     'sales_transaction_type_id' => $key->sales_transaction_type_id,
                    //     // ['between', 'transaction_date', $start_date, $end_date]
                    //     ])
                    // ->all();
                    
                $dataProvider = new \yii\data\ArrayDataProvider([
                      'allModels' => $query,
                      'pagination' => [
                          'pageSize' => 80,
                      ],
                  ]);

                $htmlContent .= $this->renderPartial('_index-report', [
                      'dataProvider' => $dataProvider,
                      'title' => $title,
                  ]);
            }
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Sales Transaction| |Fleet-21 Report|'],
            'SetFooter'=>['Sales Transaction Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'orientation' => 'ORIENT_LANDSCAPE',
                'title' => 'Sales Transaction Report No.' . $id,
                'subject' => 'Fleet 21 Sales Transaction Report No.' . $id,
                'output' => 'fleet21_sales_report.pdf',
                'keywords' => 'skymouse fleet-21 sales transaction report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    
    }

    /**
     * Finds the SalesTransaction model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SalesTransaction the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SalesTransaction::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

      public static function salesTransanctionByType()
    {        
        $sales_transaction_query = \Yii::$app->db->createCommand("SELECT  sales_transaction_type.sales_transaction_type, sales_transaction.transaction_date, COUNT(*) AS cnt, SUM(sales_transaction.transaction_amount) AS total_amout, AVG(sales_transaction.transaction_amount) AS Average 
        FROM sales_transaction_type  
        LEFT JOIN  sales_transaction
        ON sales_transaction.sales_transaction_type_id = sales_transaction_type.sales_transaction_type_id
        WHERE sales_transaction_type.record_status = 'active'
        GROUP BY sales_transaction_type.sales_transaction_type_id,sales_transaction.transaction_date 
        ")->queryAll();
        
        $x= 0;
        $date = date("Y-m-d");//"2014-01-20";//
        $sales_transaction_data['total_transactions'] = 0;
        $sales_transaction_data['today_transactions'] = 0;
        foreach($sales_transaction_query as $key => $value)
        {
            $trans_type = $value['sales_transaction_type'];
            $x++;
            //reset transation count(cnt) where there are no transaction record
            if (isset($value['transaction_date'])) {
                $cnt = intval($value['cnt']);
            } else {
                $cnt = 0;
            }
            
            //Define dynamic array index
            if (!(isset($sales_transaction_data['avg_' . $trans_type]))) $sales_transaction_data['avg_' . $trans_type] = 0;
            if (!(isset($sales_transaction_data['total_' . $trans_type]))) $sales_transaction_data['total_' . $trans_type] = 0;
            if (!(isset($sales_transaction_data['today_total_' . $trans_type]))) $sales_transaction_data['today_total_' . $trans_type] = 0;
            
            /*********Calculate dynamic index values********/
            $sales_transaction_data['total_transactions'] = $sales_transaction_data['total_transactions'] + $cnt;
            $sales_transaction_data['tmp_total_avg_' . $trans_type] = $sales_transaction_data['avg_' . $trans_type] + $value['Average'];
            $sales_transaction_data['avg_'.$trans_type] = intval($sales_transaction_data['tmp_total_avg_' . $trans_type])/$x;
            $sales_transaction_data['total_amount' . $trans_type] = $sales_transaction_data['total_' . $trans_type] + $value['total_amout'];
            
            //Todays statistics
            if ($value['transaction_date'] == $date) {
                $sales_transaction_data['today_' . $trans_type] = $sales_transaction_data['today_' . $trans_type] + $cnt;
                $sales_transaction_data['today_transactions'] = $sales_transaction_data['today_transactions'] + $cnt;
                $sales_transaction_data['today_total_' . $trans_type] = $sales_transaction_data['today_total_' . $trans_type] + $value['total_amout'];
            } else {
                $sales_transaction_data['today_' . $trans_type] = 0;
            }
            
            //calc total transaction type count
            if (isset($sales_transaction_data[$trans_type])){
                $sales_transaction_data[$trans_type] = $sales_transaction_data[$trans_type] + $cnt;
            }else {
                $sales_transaction_data[$trans_type] = $cnt;
            }
            
        }
        return $sales_transaction_data; 
            
    }

    public static function salesTransactionTypeList()
    {
        $list = SalesTransaction::getSalesTransactionTypeList();

        return $list;
    }
}
