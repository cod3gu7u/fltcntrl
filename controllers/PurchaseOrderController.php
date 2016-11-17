<?php

namespace frontend\controllers;

use Yii;
use app\models\PurchaseOrder;
use app\models\PurchaseOrderSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * PurchaseOrderController implements the CRUD actions for PurchaseOrder model.
 */
class PurchaseOrderController extends Controller
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
     * Lists all PurchaseOrder models.
     * @return mixed
     */
    public function actionIndex($creditor_id = null)
    {
        $searchModel = new PurchaseOrderSearch();

        if ($creditor_id !== null) { 
          $searchModel->creditor_id = $creditor_id;
          $searchModel->record_status = PurchaseOrder::ACTIVE;
        }

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['purchase_order_id'=>SORT_DESC],]);

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('_index', [
	            'searchModel' => $searchModel,
	            'dataProvider' => $dataProvider,
	        ]);
        } else {
            return $this->render('index', [
	            'searchModel' => $searchModel,
	            'dataProvider' => $dataProvider,
	        ]);
        }
    }

    /**
     * Displays a single PurchaseOrder model.
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
     * Creates a new PurchaseOrder model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new PurchaseOrder();
        $model->loadDefaultValues();
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');
        $model->order_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['update', 'id' => $model->purchase_order_id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing PurchaseOrder model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $this->storeReturnUrl();
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->purchase_order_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing PurchaseOrder model.
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
     * Finds the PurchaseOrder model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return PurchaseOrder the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = PurchaseOrder::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    private function storeReturnUrl()
    {
        Yii::$app->user->returnUrl = Yii::$app->request->url;
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
        $pdf->methods = [ 'SetHeader'=>['Purchase Order| |Fleet-21 Report|'], 
            'SetFooter'=>['Purchase Order Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [ 
                'title' => 'Purchase Order Report No.' . $id,
                'subject' => 'Fleet 21: Purchase Order Report No.' . $id,
                'output' => 'fleet21_purchase_order_report_'  . $id . ' .pdf',
                'keywords' => 'skymouse fleet-21 purchase order report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    public function actionConvertPopv($id)
    {
        $model = $this->findModel($id);

        $params = [
            'purchase_order_id' => $model->purchase_order_id,
            'creditor_id' => $model->creditor_id,
            'amount' => $model->billed_amount,
            ];

        return $this->redirect(['payment-voucher/create', 'params' => $params]);
    }

    public function actionFinalize($id)
    {
        // Change status Awaiting Payment
        $model = $this->findModel($id);
        $model->order_status_id = PurchaseOrder::POS_RECEIVED;
        $model->save();        
    }
}
