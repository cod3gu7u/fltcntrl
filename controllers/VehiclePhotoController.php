<?php

namespace frontend\controllers;

use Yii;
use app\models\VehiclePhoto;
use app\models\VehiclePhotoSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;

/**
 * VehiclePhotoController implements the CRUD actions for VehiclePhoto model.
 */
class VehiclePhotoController extends Controller
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
     * Lists all VehiclePhoto models.
     * @return mixed
     */
    public function actionIndex($id)
    {
        $searchModel = new VehiclePhotoSearch();
        $searchModel->vehicle_id = $id;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->renderPartial('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single VehiclePhoto model.
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
     * Creates a new VehiclePhoto model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id)
    {
        $model = new VehiclePhoto();
        $model->photograph = $id;
        $model->vehicle_id = $id;
        $model->create_user_id = \Yii::$app->user->identity->id;
        $model->create_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) ) {
            $model->file = UploadedFile::getInstance($model, 'file');
            $image_name = $model->vehicle_id . 'x' . \date('jmYHis') . '.' . $model->file->extension;
            $model->photograph = $image_name;

            $model->file->saveAs('frontend/web/uploads/vehicles/' . $image_name);
            $model->save();

            return $this->redirect(['vehicle/update', 'id' => $model->vehicle_id]);
        } else {
            return $this->renderAjax('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing VehiclePhoto model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->vehicle_photo_id]);
        } else {
            return $this->renderAjax('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing VehiclePhoto model.
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
     * Finds the VehiclePhoto model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return VehiclePhoto the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = VehiclePhoto::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
