<?php

namespace frontend\controllers;

use Yii;
use app\models\SalesQuote;
use app\models\SalesQuoteSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * SalesQuoteController implements the CRUD actions for SalesQuote model.
 */
class SalesQuoteController extends Controller
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
     * Lists all SalesQuote models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SalesQuoteSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->setSort(['defaultOrder' => ['sales_quote_id'=>SORT_DESC],]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single SalesQuote model.
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
     * Creates a new SalesQuote model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $user_id = \Yii::$app->user->identity->id;

        $model = new SalesQuote();
        $model->loadDefaultValues();
        $model->create_user_id = $user_id;
        $model->create_date = date('Y-m-d');
        $model->quote_issue_date = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->sales_quote_id]);
        } elseif (Yii::$app->request->isAjax) {
            return $this->renderAjax('_form', [
                        'model' => $model
            ]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing SalesQuote model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->sales_quote_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing SalesQuote model.
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
     * Finds the SalesQuote model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return SalesQuote the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = SalesQuote::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

        public function actionReturn($id)
    {
        $model = $this->findModel($id);
        if(!Sales::reverseSales($model)){
            throw new \yii\web\NotFoundHttpException;
        }
        return $this->redirect(['update', 'id'=>$id]);
    }

    public function actionReport($id = null)
    {
        $htmlContent = '';

        if($id){
            $model = $this->findModel($id);
            // Set page headers
            $htmlContent .= $this->renderPartial('@app/views/report/_header');
            // get your HTML raw content without any layouts or scripts
            $htmlContent .= $this->renderPartial('_report',[
                    'model' => $model,
                ]);
        } 

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Sales| |F-21 Report|'],
            'SetFooter'=>['Sales Quote|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                'title' => 'Sales Quote Report No.' . $id,
                'subject' => 'Fleet 21 Sales Quote Report No.' . $id,
                'output' => 'fleet21_sales_quote_report.pdf',
                'keywords' => 'skymouse fleet-21 sales quote report',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
    }
}
