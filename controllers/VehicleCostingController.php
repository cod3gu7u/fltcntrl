<?php

namespace frontend\controllers;

use Yii;
use app\models\VehicleCosting;
use app\models\VehicleCostingSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * VehicleCostingController implements the CRUD actions for VehicleCosting model.
 */
class VehicleCostingController extends Controller
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
     * Lists all VehicleCosting models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new VehicleCostingSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single VehicleCosting model.
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
     * Creates a new VehicleCosting model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id)
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new VehicleCosting();
        $model->vehicle_id = $id;
        $model->create_user_id = $user_id;
        $model->cost_date = date('Y-m-d');
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // return $this->redirect(['index']);
            return $this->goBack();
        } else {
            return $this->renderAjax('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing VehicleCosting model.
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
            return $this->redirect(['index']);
        } else {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing VehicleCosting model.
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
     * Finds the VehicleCosting model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return VehicleCosting the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = VehicleCosting::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionListReport(array $params = array())
    {
        $title = 'Vehicle Costing by Date Report: ';
        $query = VehicleCosting::find();

        if(isset($params['start_date'])){
            $start_date = $params['start_date'];
            $end_date = isset($params['end_date']) ? $params['end_date'] : $start_date;

            $query = $query->where(['between', 'cost_date', $start_date, $end_date]);

            $title .= $start_date . ' - ' . $end_date;
        }


        $dataProvider = new \yii\data\ActiveDataProvider([
              'query' => $query,
              'pagination' => [
                  'pageSize' => 80,
              ],
          ]);

        $htmlContent = $this->renderPartial('_index-report', [
              'dataProvider' => $dataProvider,
              'title' => $title,
          ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['vehicle| |Fleet-21 Report|'],
            'SetFooter'=>['vehicle Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'orientation' => 'ORIENT_LANDSCAPE',
                'title' => 'vehicle Report No.' . $id,
                'subject' => 'Fleet 21 vehicle Report No.' . $id,
                'output' => 'fleet21_vehicle_report.pdf',
                'keywords' => 'skymouse fleet-21 vehicle report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }
}
