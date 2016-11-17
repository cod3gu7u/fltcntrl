<?php

namespace frontend\controllers;

use Yii;
use app\models\PaymentVoucher;
use app\models\PaymentVoucherSearch;
use yii\web\Session;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\DocumentCounters;
use app\models\GLPosting;
use app\models\Cashbook;
use app\models\PurchaseOrder;

/**
 * PaymentVoucherController implements the CRUD actions for PaymentVoucher model.
 */
class PaymentVoucherController extends Controller
{
    const PAYABLES_JOURNAL = 'PAYMNT'; 
    const PAYABLES_JOURNAL_ID = 6; 
    const JOURNAL_TYPE_ID = 2;
    const TRANSACTION_TYPE = 1; // payment

    /**
     * @inheritdoc
     */
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
     * Lists all PaymentVoucher models.
     * @return mixed
     */
    public function actionIndex($creditor_id = null)
    {
        $searchModel = new PaymentVoucherSearch();

        if ($creditor_id !== null) { 
          $searchModel->creditor_id = $creditor_id;
          // $searchModel->record_status = self::ACTIVE;
        }

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['payment_voucher_id'=>SORT_DESC],]);
        $dataProvider->pagination->pageSize=10;

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single PaymentVoucher model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new PaymentVoucher model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate(array $params = array())
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new PaymentVoucher();
        $model->loadDefaultValues();

        if(isset($params['creditor_id'])){
            $model->creditor_id = $params['creditor_id'];
        }
        if(isset($params['amount'])){
            $model->amount = $params['amount'];
        }

        $model->pv_date = date('Y-m-d');
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) ) {
            $model->pv_number = 'PV-' . DocumentCounters::nextCounters();

            if(isset($params['purchase_order_id'])){
                if (($po_model = PurchaseOrder::findOne($params['purchase_order_id'])) !== null) {
                    $po_model->order_status_id = PurchaseOrder::POS_PO2PV;
                    $po_model->record_status = PurchaseOrder::INACTIVE;
                    $po_model->update_user_id = $user_id;
                    $po_model->update_date = date('Y-m-d');
                    $po_model->save();
                } 
            }

            $model->save();
            // print_r($model->getErrors()); die();
            return $this->redirect(['view', 'id' => $model->payment_voucher_id]);
        } elseif (Yii::$app->request->isAjax) {
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
     * Updates an existing PaymentVoucher model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->payment_voucher_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing PaymentVoucher model.
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
    * Make a posting to both cashbook and gl
    */
    public function actionPost($id)
    {
        $this->actionGlPost($id);
        $this->actionGlPost($id);
    }

    /**
    * Create a GL Post for a Payables Invoice
    * 1. Create Batch -> 2. Create Journal -> 3. Populate Journal Posting
    **/
    public function actionGlPost($id)
    {
        if(($model = $this->findModel($id)) !== null)
        {
            $glposting = new GLPosting();
            try{
                $user_id = \Yii::$app->user->identity->id;
                $batch_prefix = self::PAYABLES_JOURNAL;

                // create batch
                $batch_id = $glposting->createBatch($batch_prefix);
                
                // create journal
                $params = ['batch_id'=>$batch_id,'journal_type_id'=>self::JOURNAL_TYPE_ID];
                $journal_id = $glposting->createJournal($params);
                $accounts = $glposting->getDebitCreditAccounts(self::PAYABLES_JOURNAL_ID);

                // Get current accounting period
                $accounting_period = \app\models\AccountingPeriod::getCurrentAccountingPeriod();

                $params_debit = [
                    'journal_id' => $journal_id,
                    'unit_amount' => $model->final_amount,
                    'account_id' => $accounts->debit_account_id,
                    'accounting_period_id' => $accounting_period->accounting_period_id,
                    'create_user_id' => $model->create_user_id,
                    ];

                $params_credit = [
                    'journal_id' => $journal_id,
                    'unit_amount' => ($model->final_amount * -1),
                    'account_id' => $accounts->credit_account_id,
                    'accounting_period_id' => $accounting_period->accounting_period_id,
                    'create_user_id' => $model->create_user_id,
                    ];

                $glposting->populateJournal($params_debit);
                $glposting->populateJournal($params_credit);

                // Update model posting status
                $model->posting_status = 'posted';
                $model->save();

                $session = new Session;
                $session->addFlash("info","Transaction successfully posted.");

                return $this->redirect(['index']);

            }catch(\Exception $e){
                // throw $e;
                throw new NotFoundHttpException('Operation failed to complete.' . $e);
            } 
        }
    }

    public function actionCbPost($id)
    {
        if (($model = $this->findModel($id)) !== null) {
            $tax_amount = ($model->tax_rate * (1 + $model->amount));
            $open_year = \app\models\AccountingPeriod::getCurrentAccountingPeriod();
            
            $params['bank_id'] = $model->bank_id;           
            $params['accounting_period_id'] = $open_year->accounting_period_id;           
            $params['transaction_date'] = $model->pv_date;           
            $params['transaction_type_id'] = self::TRANSACTION_TYPE;           
            $params['account_id'] = $model->bank->account_id;           
            $params['payer_payee_id'] = $model->creditor_id;
            $params['reference_number'] = $model->pv_number;           
            $params['exclusive_amount'] = $model->amount;           
            $params['tax_amount'] = $tax_amount;           
            $params['total_amount'] = $model->final_amount;           
            $params['create_user_id'] = $model->create_user_id;
            $params['create_date'] = date('Y-m-d');

            // Make cashbook entry
            Cashbook::cashbookEntry($params);
            return $this->redirect(['index']);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    /**
     * Finds the PaymentVoucher model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return PaymentVoucher the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = PaymentVoucher::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


    public function actionReport($id)
    {
        $model = $this->findModel($id);
        $htmlContent = '';
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
        $pdf->methods = [ 'SetHeader'=>['Payment Voucher | |Fleet-21 Report|'],
            'SetFooter'=>['Payment Voucher|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'title' => 'Payment Voucher No.' . $id,
                'subject' => 'Fleet 21 Payment Voucher No.' . $id,
                'output' => 'pv_document_' . $id . '.pdf',
                'keywords' => 'skymouse fleet-21 Payment Voucher',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

}
