<?php

namespace frontend\controllers;

use Yii;
use app\models\Customer;
use app\models\CustomerSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\data\Pagination;

/**
 * CustomerController implements the CRUD actions for Customer model.
 */
class CustomerController extends Controller
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
     * Lists all Customer models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CustomerSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['customer_id'=>SORT_DESC],]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Customer model.
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
     * Creates a new Customer model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new Customer();
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) ) {
            Yii::$app->response->format = 'json';

            if ($model->validate() && $model->save()) {
                // all inputs are valid              
                return [
                 'customer_id'=>$model->customer_id, 
                 'customer_name' => $model->customer_name
                ];
            } else {
                // validation failed: $errors is an array containing error messages
                $errors = $model->errors;
                // print_r($model);
                // die();
                return ['message'=>$errors,];
            }

            // return $this->redirect(['index']);
        } else {
            return $this->renderAjax('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Customer model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        // Get logged in user_id
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
     * Deletes an existing Customer model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    public function actionLatestCustomer()
    {
        $countCustomer = Customer::find()->orderBy(['customer_id' => SORT_DESC])->count();
        $customer = Customer::find()->orderBy(['customer_id' => SORT_DESC])->one();
        if($countCustomer>0)
        {
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            $data = ['id'=>$customer->customer_id, 'name' => $customer->customer_name];
            return $data;
        }
    }

    public function actionListReport()
    {
        $query = Customer::find();

        $pagination = new Pagination([
            'defaultPageSize' => 1000,
            'totalCount' => $query->count(),
        ]);

        $customers = $query->orderBy('customer_id')
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        $htmlContent = $this->renderPartial('_index-report', [
            'customers' => $customers,
            'pagination' => $pagination,
        ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Customer| |Fleet-21 Report|'],
            'SetFooter'=>['Customer Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'orientation' => 'ORIENT_LANDSCAPE',
                'title' => 'Customer Report No.' . $id,
                'subject' => 'Fleet 21 Customer Report No.' . $id,
                'output' => 'fleet21_customer_report.pdf',
                'keywords' => 'skymouse fleet-21 sales report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }

    /**
     * Finds the Customer model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Customer the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Customer::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
