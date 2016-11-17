<?php

namespace frontend\controllers;

use Yii;
use app\models\Bankbook;
use app\models\BankbookSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;

/**
 * BankbookController implements the CRUD actions for Bankbook model.
 */
class BankbookController extends Controller
{
    const PAYMENT_TRNX = 1;
    const RECEIPT_TRNX = 2;

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
     * Lists all Bankbook models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new BankbookSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Bankbook model.
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
     * Creates a new Bankbook model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Bankbook();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->bankbook_entry_id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Bankbook model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->bankbook_entry_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    public function actionPayerPayee() 
    {
        // $out = \app\models\Cashbook::getCustomerList();
        // echo Json::encode(['output'=>$out, 'selected'=>'']);
        
        $status = isset($_POST['depdrop_parents']); 
        if($status){
            $parents = $_POST['depdrop_parents'];
            if($parents != null){
                $transaction_type_id = $parents[0];
                if($transaction_type_id == self::PAYMENT_TRNX) {
                    $out = \app\models\Cashbook::getCreditorList();
                } else if($transaction_type_id == self::RECEIPT_TRNX) {
                    $out = \app\models\Cashbook::getCustomerList();
                } 

                echo Json::encode(['output'=>$out, 'selected'=>'']);
                return;
            }
        } 
        echo Json::encode(['output'=>$status, 'selected'=>'-']);
    }

    /**
     * Deletes an existing Bankbook model.
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
     * Finds the Bankbook model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Bankbook the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Bankbook::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
