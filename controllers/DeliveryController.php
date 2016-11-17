<?php

namespace frontend\controllers;

use Yii;
use app\models\Delivery;
use app\models\DeliverySearch;
use app\models\Sales;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;

/**
 * DeliveryController implements the CRUD actions for Delivery model.
 */
class DeliveryController extends Controller
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
     * Lists all Delivery models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new DeliverySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Delivery model.
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
     * Creates a new Delivery model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new Delivery();
        $model->create_user_id = $user_id;
        $model->delivery_date = date('Y-m-d');
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post())) {
            $model->sales_id = $this->findSalesID($model->vehicle_id);
            $model->save();
            // Print the report
            //return $this->redirect(['report', 'id' => $model->delivery_id, 'target' => '_blank',]);
            return $this->redirect(['view', 'id' => $model->delivery_id]);

        } else {
          if (Yii::$app->request->isAjax) {
              return $this->renderAjax('create', [
                  'model' => $model,
              ]);
          } else {
              return $this->render('create', [
                  'model' => $model,
              ]);
          }
        }
    }

    /**
     * Updates an existing Delivery model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->delivery_id]);
        } else {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Delivery model.
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
     * Finds the Delivery model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Delivery the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Delivery::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    private function findSalesID($vehicle_id)
    {
        $sales = Sales::find()
          ->where(['record_status'=>Sales::ACTIVE, 'vehicle_id'=>$vehicle_id])
          ->one();
        return $sales->sales_id;
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
        $pdf->methods = [ 'SetHeader'=>['Delivery| |Fleet-21 Report|'],
            'SetFooter'=>['Skymouse Fleet-21 Delivery Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'title' => 'Delivery Report No.' . $id,
                'subject' => 'Fleet 21 Delivery Report No.' . $id,
                'output' => 'fleet21_delivery_report.pdf',
                'keywords' => 'skymouse fleet-21 delivery report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

}
