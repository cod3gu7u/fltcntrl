<?php

namespace frontend\controllers;

use Yii;
use app\models\VehicleExchange;
use app\models\VehicleExchangeSearch;
use app\models\Sales;
use app\models\Vehicle;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * VehicleExchangeController implements the CRUD actions for VehicleExchange model.
 */
class VehicleExchangeController extends Controller
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
     * Lists all VehicleExchange models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new VehicleExchangeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single VehicleExchange model.
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
     * Creates a new VehicleExchange model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @param $sales_id
     * @return mixed
     */
    public function actionCreate($id)
    {
        $user_id = \Yii::$app->user->identity->id;

        $sales = Sales::findOne(['sales_id' => $id ]);
        $original_vehicle_id = $sales->vehicle_id;
        $reference_number = $sales->vehicle->reference_number;

        $model = new VehicleExchange();
        $model->original_sales_id = $id;
        $model->original_vehicle_id = $original_vehicle_id;
        $model->exchange_date = date('Y-m-d');
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // Swap vehicles
            $old_vehicle = Vehicle::findOne(['vehicle_id' => $original_vehicle_id]);
            $old_vehicle->stock_status_id = Vehicle::INSTOCK_STATUS;
            $old_vehicle->record_status = Vehicle::ACTIVE;
            $old_vehicle->save();

            $new_vehicle = Vehicle::findOne(['vehicle_id' => $model->new_vehicle_id]);
            $new_vehicle->stock_status_id = Vehicle::RESERVED_STATUS;
            $new_vehicle->record_status = Vehicle::ACTIVE;
            $new_vehicle->save();

            // Recalculate Balance
            $sales->original_sales_amount = $model->new_sales_amount;
            $sales->discount_amount = 0.00;
            $sales->final_sales_amount = $model->new_sales_amount;
            $sales->vehicle_id = $model->new_vehicle_id;
            $sales->balance = ($model->new_sales_amount - $sales->paid_amount);
            $sales->notes = date('Y-m-d') . ' : Vehicle Exchange \n' . $sales->notes;
            $sales->update_user_id = $user_id;
            $sales->update_date = date('Y-m-d');
            $sales->save();

            return $this->redirect(['sales/update', 'id' => $id]);
        } else {
            if (Yii::$app->request->isAjax) {
                return $this->renderAjax('create', [
                  'model' => $model,
                  'reference_number' => $reference_number,
                ]);
            } else {
                return $this->render('create', [
                  'model' => $model,
                  'reference_number' => $reference_number,
                ]);
            }
        }
    }

    /**
     * Updates an existing VehicleExchange model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->vehicle_exchange_id]);
        } else {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing VehicleExchange model.
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
     * Finds the VehicleExchange model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return VehicleExchange the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = VehicleExchange::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
