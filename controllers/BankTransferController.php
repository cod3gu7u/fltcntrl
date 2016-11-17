<?php

namespace frontend\controllers;

use Yii;
use app\models\BankTransfer;
use app\models\BankTransferSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\Batch;
use app\models\Journal;
use app\models\Bank;
use app\models\AccountingPeriod;
use app\models\Posting;


/**
 * BankTransferController implements the CRUD actions for BankTransfer model.
 */
class BankTransferController extends Controller
{
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
     * Lists all BankTransfer models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new BankTransferSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['bank_transfer_id'=>SORT_DESC],]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single BankTransfer model.
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
     * Creates a new BankTransfer model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $user_id = \Yii::$app->user->identity->id;
        
        $model = new BankTransfer();
        $model->loadDefaultValues();
        $model->transfer_date = date('Y-m-d');
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {

            $this->doAfter($model->bank_transfer_id);

            // return $this->redirect(['index']);
        } elseif (Yii::$app->request->isAjax) {
            return $this->renderAjax('create', [
                'model' => $model,
            ]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    public function doAfter($id)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try 
        {
            $model = $this->findModel($id);

            // Create Batch
            $batch_prefix = 'TRANSFER';
            // $batch_id = Batch::createBatch($batch_prefix);
            // $batch_id = Batch::createBatch($batch_prefix);
            // $batch = new BatchController();
            // $batch_id = $batch->createBatch($batch_prefix);

            $batch_name = $batch_prefix . date('Ymd') . '-' . \Yii::$app->user->identity->id;

            $batch = new Batch();
            // $batch->loadDefaultValues();
            $batch->batch_name = $batch_name;
            $batch->batch_date = date('Y-m-d');
            $batch->save();

            $batch_id = $batch->batch_id;


            // create journal
            $journal_type_id = Journal::TRANSFER_JOURNAL_TYPE;
            $params = ['batch_id'=>$batch_id, 'journal_type_id'=>$journal_type_id];
            $journal_id = Journal::createJournal($params);

            // print_r($batch_id . ' expression ' . $journal_id); die();
            
            // Get associated GL Posting Accounts
            $source_bank_account_id = Bank::find()
                    ->where(['bank_id' => $model->source_bank_id])
                    ->one();

            $destination_bank_account_id = Bank::find()
                    ->where(['bank_id' => $model->destination_bank_id])
                    ->one();
            
            // Get current accounting period
            $accounting_period = AccountingPeriod::findOne([
                'status'=>1,
                'record_status'=>'active']);

            $params_debit = [
                'journal_id' => $journal_id,
                'unit_amount' => $model->amount,
                'account_id' => $source_bank_account_id->account_id,
                'accounting_period_id' => $accounting_period->accounting_period_id,
                'create_user_id' => $model->create_user_id,
                ];

            $params_credit = [
                'journal_id' => $journal_id,
                'unit_amount' => ($model->amount * -1),
                'account_id' => $destination_bank_account_id->account_id,
                'accounting_period_id' => $accounting_period->accounting_period_id,
                'create_user_id' => $model->create_user_id,
                ];

            $debit_result = Posting::populateJournal($params_debit);
            $credit_result = Posting::populateJournal($params_credit);

            $transaction->commit();

            return $this->redirect(['view', 'id' => $id]);

        } catch (\Exception $exc) {
            $transaction->rollBack();
            throw $exc;
        }
        $transaction->rollBack();
    }

    /**
     * Updates an existing BankTransfer model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } else {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing BankTransfer model.
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
     * Finds the BankTransfer model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return BankTransfer the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BankTransfer::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
