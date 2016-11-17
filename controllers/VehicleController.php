<?php

namespace frontend\controllers;

use Yii;
use app\models\Vehicle;
use app\models\VehicleSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\ReferenceNumber;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

/**
 * VehicleController implements the CRUD actions for Vehicle model.
 */
class VehicleController extends Controller
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
     * Lists all Vehicle models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new VehicleSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $dataProvider->setSort(['defaultOrder' => ['vehicle_id'=>SORT_DESC],]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Lists all Vehicle models.
     * @return mixed
     */
    public function actionList()
    {
        $searchModel = new VehicleSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['vehicle_id'=>SORT_DESC],]);

        return $this->render('list', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Vehicle model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax('view', [
                'model' => $model
            ]);
        } else {
            return $this->render('view', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Creates a new Vehicle model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        // Store return Url
        $this->storeReturnUrl();

        $user_id = \Yii::$app->user->identity->id;

        $model = new Vehicle();
        $model->loadDefaultValues();
        $model->create_user_id = $user_id;
        $model->arrival_date = date('Y-m-d');
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post())) {
            // get reference number
            $reference_prefix = $model->reference_number;
            $model->reference_number = $this->getReferenceNumber($reference_prefix);
            $model->save();
            // increment reference number
            $this->updateCounters($reference_prefix);

            return $this->redirect(['index']);
            // return $this->goBack();
        } elseif (Yii::$app->request->isAjax) {
            return $this->renderAjax('create', [
                'model' => $model
            ]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Vehicle model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        // Store return Url
        $this->storeReturnUrl();

        // Get logged in user_id
        $user_id = \Yii::$app->user->identity->id;

        $model = $this->findModel($id);
        $model->update_user_id = $user_id;
        $model->update_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->goBack();
        } elseif (Yii::$app->request->isAjax) {
            return $this->renderAjax('update', [
                'model' => $model
            ]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Vehicle model.
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
     * Finds the Vehicle model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Vehicle the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Vehicle::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionGetVehicleDetails($id)
    {
        $model = $this->findModel($id);
        echo Json::encode($model);
    }

    public function actionVehicleModels() 
    {
        $status = isset($_POST['depdrop_parents']); 
        $out = [];
        if ($status) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $make_id = $parents[0];
                $out = Vehicle::getVehicleModelList($make_id); 
                // the getSubCatList function will query the database based on the
                // cat_id and return an array like below:
                // [
                //    ['id'=>'<sub-cat-id-1>', 'name'=>'<sub-cat-name1>'],
                //    ['id'=>'<sub-cat_id_2>', 'name'=>'<sub-cat-name2>']
                // ]
                // echo $out[0];
                echo Json::encode(['output'=>$out, 'selected'=>'']);
                return;
            }
        }
        echo Json::encode(['output'=>$status, 'selected'=>'-']);
    }

    private function getReferenceNumber($reference_prefix)
    {
        if (($model = ReferenceNumber::find()
            ->where(['reference_prefix' => $reference_prefix])
            ->one()) !== null) {
            return $reference_prefix . ' ' . $model->reference_counter;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    private function updateCounters($reference_prefix)
    {
        if (($model = ReferenceNumber::find()
            ->where(['reference_prefix' => $reference_prefix])
            ->one()) !== null)
        {
            $model->updateCounters(['reference_counter' => 1]);
        } else {
            throw new NotFoundHttpException('The requested page does not exist..' . $reference_prefix);
        }
    }

    public function actionListReport($filter = null)
    {
        $query = Vehicle::find();
        $title = 'Vehicle: ' . ucfirst($filter);
        
        switch($filter)
        {
            case 'instock':
                $query = $query->where(['stock_status_id'=>Vehicle::INSTOCK_STATUS]);
                break;
            case 'reserved':
                $query = $query->where(['stock_status_id'=>Vehicle::RESERVED_STATUS]);
                break;
            case 'sold':
                $query = $query->where(['stock_status_id'=>Vehicle::SOLD_STATUS]);
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
        $pdf->methods = [ 'SetHeader'=>['Vehicle| |Fleet-21 Report|'],
            'SetFooter'=>['Vehicle Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'orientation' => 'ORIENT_LANDSCAPE',
                'title' => 'Vehicle Report No.' . $id,
                'subject' => 'Fleet 21 Vehicle Report No.' . $id,
                'output' => 'fleet21_vehicle_report.pdf',
                'keywords' => 'skymouse fleet-21 sales report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    public function actionStickerReport($id)
    {
        $model = $this->findModel($id);

        $htmlContent = $this->renderPartial('@app/views/report/_header');

        $htmlContent .= $this->renderPartial('_sticker', [
              'model' => $model,
          ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        // $pdf->methods = [ 'SetHeader'=>['Vehicle| |Fleet-21 Report|'],
        //     'SetFooter'=>['Vehicle Report|{PAGENO}|{DATE j-F-Y}'],
        // ];
        $pdf->content = $htmlContent;
        // $pdf->options =  [
        //         'orientation' => 'ORIENT_LANDSCAPE',
        //         'title' => 'Vehicle Report No.' . $id,
        //         'subject' => 'Fleet 21 Vehicle Report No.' . $id,
        //         'output' => 'fleet21_vehicle_report.pdf',
        //         'keywords' => 'skymouse fleet-21 sales report',
        //         'author' => 'SKYMOUSE Fleet-21',
        //         'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    private function storeReturnUrl()
    {
        Yii::$app->user->returnUrl = Yii::$app->request->url;
    }

    public function actionCatalog()
    {
        \Yii::$app->response->format = 'json';

        $connection = Yii::$app->getDb();
        $vehicle_data = $connection->createCommand("select reference_number, chassis, engine, color, supplier, location, make, vehicle_type, model, model_year, capacity, arrival_date, arrival_mileage, transmission, fule_type, asking_price, vehicle.notes, stock_status 
        from vehicle  
        left join supplier on vehicle.supplier_id = supplier.supplier_id
        left join location on vehicle.location_id = location.location_id
        left join vehicle_make on vehicle.make_id = vehicle_make.vehicle_make_id
        left join vehicle_type on vehicle.vehicle_type_id = vehicle_type.vehicle_type_id
        left join vehicle_model on vehicle.model_id = vehicle_model.model_id
        left join vehicle_transmission on vehicle.transmission_id = vehicle_transmission.id
        left join fuel_type on vehicle.fuel_type_id = fuel_type.id
        left join stock_status on vehicle.stock_status_id = stock_status.stock_status_id
        left join color on vehicle.color_id = color.color_id
        where vehicle.record_status = 'active' and vehicle.stock_status_id in (2,5)");

        $photos = $connection->createCommand("select reference_number, photograph 
        from vehicle left join vehicle_photo on vehicle.vehicle_id = vehicle_photo.vehicle_id 
        where vehicle.record_status = 'active' and vehicle.stock_status_id in (2,5)
        and photograph is not null");

        return ['vehicle' => $vehicle_data->queryAll(), 'photos' => $photos->queryAll()];
        // print_r($command->queryAll()); die();
    }

    public function actionStockStatus()
    {
        // \Yii::$app->response->format = 'json';

        $vehicle = Vehicle::find()
            ->select(['make_id', 'COUNT(*) AS cnt'])
            ->groupBy(['make_id'])
            ->asArray()
            ->all();

        return $this->render('_stock-status', [
                'model' => $vehicle,
            ]);
    }

    public static function vehiclesCountByStatus()
    {             
         $results = \Yii::$app->db->createCommand("
         SELECT sales.sales_id, sales.vehicle_id, vehicle.stock_status_id, stock_status.stock_status, sales.sales_date
         from sales
         left join vehicle on sales.vehicle_id = vehicle.vehicle_id
         left join stock_status on stock_status.stock_status_id = vehicle.stock_status_id
         where sales.vehicle_id is not null and vehicle.vehicle_id is not null
         "); //where vehicle.record_status = 'active'//group by vehicle.stock_status_id
         
         $results = $results->queryAll();
          
        $x= 0;
        $date = date("Y-m-d");//"2014-01-20"
        $vehiclesStatusCount = array();
        foreach($results as $value)
        {
            $stock_status = trim($value['stock_status']);
            
            if (!(isset($vehiclesStatusCount[$stock_status]))) { $vehiclesStatusCount['today_'.$stock_status] = 0; }
            if (!(isset($vehiclesStatusCount[$stock_status]))) { $vehiclesStatusCount[$stock_status] = 0; }
            
            if (isset($value['stock_status'])) {
                if ($value['sales_date'] == $date) {
                    $vehiclesStatusCount['today_'.$stock_status] = $vehiclesStatusCount['today_'.$stock_status] + 1;
                }
                $vehiclesStatusCount[$stock_status] = $vehiclesStatusCount[$stock_status] + 1;
            }
        }
          
        return $vehiclesStatusCount;
    }

        public function actionPeriodicalReport(array $params = array())
    {
        $title = 'Vehicle Arrival by Date Report: ';
        $query = Vehicle::find();

        if(isset($params['start_date'])){
            $start_date = $params['start_date'];
            $end_date = isset($params['end_date']) ? $params['end_date'] : $start_date;

            $query = $query->where(['between', 'arrival_date', $start_date, $end_date]);

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
