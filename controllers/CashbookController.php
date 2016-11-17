<?php

namespace frontend\controllers;

use Yii;
use app\models\Cashbook;
use app\models\CashbookSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\base\ErrorException;
use yii\filters\VerbFilter;
use yii\helpers\Json;


/**
 * CashbookController implements the CRUD actions for Cashbook model.
 */
class CashbookController extends Controller
{
    const PAYMENT_TRNX = 1;
    const RECEIPT_TRNX = 2;
    const TRANSFER_TRNX = 3;

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
     * Lists all Cashbook models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CashbookSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['cashbook_entry_id'=>SORT_DESC],]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Cashbook model.
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
     * Creates a new Cashbook model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new Cashbook();
        $model->loadDefaultValues();
        $model->transaction_date = date('Y-m-d');
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->cashbook_entry_id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Cashbook model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        // if ($model->load(Yii::$app->request->post()) && $model->save()) {
        if ($model->load(Yii::$app->request->post())) {
            if($model->getError() !== null){
                        $model->save();
                        die('saved');
                    }else {
                        print_r($model->getError());
                        die();
                    }
            return $this->redirect(['view', 'id' => $model->cashbook_entry_id]);
        } else {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
    }

    public function actionPayerPayee() 
    {
        $status = isset($_POST['depdrop_parents']); 
        if($status){
            $parents = $_POST['depdrop_parents'];
            if($parents != null){
                $transaction_type_id = $parents[0];
                if($transaction_type_id == self::PAYMENT_TRNX) {
                    $out = Cashbook::getCreditorList();
                } else if($transaction_type_id == self::RECEIPT_TRNX) {
                    $out = Cashbook::getCustomerList();
                } else if($transaction_type_id == self::TRANSFER_TRNX) {
                    $out = Cashbook::getTransferBank();
                } 

                echo Json::encode(['output'=>$out, 'selected'=>'']);
                return;
            }
        } 
        echo Json::encode(['output'=>$status, 'selected'=>'-']);
    }

    /**
     * Deletes an existing Cashbook model.
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
     * Finds the Cashbook model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Cashbook the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Cashbook::findOne($id)) !== null) {
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
        $pdf->methods = [ 'SetHeader'=>['Cashbook | |Fleet-21 Report|'],
            'SetFooter'=>['Skymouse Fleet-21 Cashbook Dcoument|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'title' => 'Cashbook Document No.' . $id,
                'subject' => 'Fleet 21 Cashbook Document No.' . $id,
                'output' => 'fleet21_cashbook_document_' . $id . '.pdf',
                'keywords' => 'skymouse fleet-21 cashbook document',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    public function actionListReport($id)
    {
        $dataProvider = new \yii\data\ActiveDataProvider([
                  'query' => Cashbook::find()->where(['bank_id' => $id]),
                  'pagination' => [
                      'pageSize' => 80,
                  ],
              ]);

        $htmlContent = $this->renderPartial('_index-report', ['dataProvider' => $dataProvider,
          ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        // $pdf->methods = [ 'SetHeader'=>['Vehicle| |Fleet-21 Report|'],
        //     'SetFooter'=>['Report|{PAGENO}|{DATE j-F-Y}'],
        // ];
        $pdf->content = $htmlContent;
        // $pdf->options =  [
        //         'orientation' => 'ORIENT_LANDSCAPE',
        //         'title' => 'Vehicle Report No.' . $id,
        //         'subject' => 'Fleet 21 Vehicle Report No.' . $id,
        //         'output' => 'fleet21_sales_report.pdf',
        //         'keywords' => 'skymouse fleet-21 sales report',
        //         'author' => 'SKYMOUSE Fleet-21',
        //         'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }
}
