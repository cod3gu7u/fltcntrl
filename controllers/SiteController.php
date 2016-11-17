<?php
namespace frontend\controllers;

use Yii;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use app\models\Vehicle;
use app\models\VehicleType;
use app\models\VehicleMake;
use app\models\Sales;
use yii\db\Expression;
use kartik\mpdf\Pdf;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function beforeAction($action)
    {

        if (!parent::beforeAction($action)) {
            return false;
        }

        // Check only when the user is logged in
        if ( !Yii::$app->user->isGuest)  {
            if (Yii::$app->session['userSessionTimeout'] < time()) {
                Yii::$app->user->logout();
            } else {
                Yii::$app->session->set('userSessionTimeout', time() + Yii::$app->params['sessionTimeoutSeconds']);
                return true; 
            }
        } else {
            return true;
        }
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
		\Yii::$app->formatter->locale = 'mn-CN'; //language code like for english --> en-Us
		
        if (!\Yii::$app->user->isGuest) {
    
			$vehiclebymake =  $this->vehiclebymake();
			$vehiclebytype = $this->Vehiclebytype();
			$salesPerDay = $this->salesPerDay();
			$salesPerMonth = $this->salesPerMonth();
			$salesCountByType_data = $this->salesCountByType();
			$salesCountByType = $salesCountByType_data['results'];
			$monthsArray = $salesCountByType_data['monthsArray'];
			$vehiclesByStockStatus = $this->vehiclesByStockStatus();
			
			$sales = Sales::find()
			->select( 'MONTH(sales_date) AS m_date,sales_date AS date, SUM(final_sales_amount) AS Total, COUNT(*) AS Sold' )
			->andFilterWhere(['void' => ''])
			->andFilterWhere(['BETWEEN', 'sales_date',
                new \yii\db\Expression('(NOW() - INTERVAL 4 MONTH)'),
                new \yii\db\Expression('NOW()')
            ])
            ->groupBy ('m_date')
			->asArray()
            ->all();
           
            return $this->render('index',[
				'make' => $vehiclebymake,
				'type' => $vehiclebytype, 
				//'sales' => $sales,
				'salesByType' => $salesCountByType,
				'monthsArray' => $monthsArray,
				'salesPerDay' => $salesPerDay,
				'salesPerMonth' => $salesPerMonth,
				'vehiclesByStockStatus' => $vehiclesByStockStatus
				
			]);
        }
        return $this->redirect(['site/login']);
   
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        // Set the user session timeout
        Yii::$app->session->set('userSessionTimeout', time() + Yii::$app->params['sessionTimeoutSeconds']);

        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            // return $this->goHome();
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->redirect(['site/login']);
        // return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return mixed
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail(Yii::$app->params['adminEmail'])) {
                Yii::$app->session->setFlash('success', 'Thank you for contacting us. We will respond to you as soon as possible.');
            } else {
                Yii::$app->session->setFlash('error', 'There was an error sending email.');
            }

            return $this->refresh();
        } else {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Displays about page.
     *
     * @return mixed
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($user = $model->signup()) {
                if (Yii::$app->getUser()->login($user)) {
                    return $this->goHome();
                }
            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->session->setFlash('success', 'Check your email for further instructions.');

                return $this->goHome();
            } else {
                Yii::$app->session->setFlash('error', 'Sorry, we are unable to reset password for email provided.');
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }
    
	public function Vehiclebymake()
    {
        $vehicle = Vehicle::find()
            ->select(['make_id', 'COUNT(*) AS cnt'])
            ->groupBy(['make_id'])
            ->asArray()
            ->all();
            
		$vehicletype = VehicleMake::find()
            ->select(['vehicle_make_id','make'])
            ->asArray()
            ->all();
            
		$newArray = array();
		//$newArray['Make'][] = 'Number';
		$newArray[] = array('Make','count');
		foreach($vehicletype as $key => $value)
		{							
			foreach($vehicle as $key3 => $value3)
			{
				if(isset($value3['make_id']))
				{
					if($value3['make_id'] == $value['vehicle_make_id'])
					{
						$make = $value['make'];
						$cnt = intval($value3['cnt']);
						//$newArray[][$make][] = $value3['cnt'];
						$newArray[] = array($make,$cnt);
						//array_push($newArray["$make"], $value3['cnt']);
					}
				}
			}
		}
		return $newArray;
        
    }
	
	public function Vehiclebytype()
	{	
			$vehicle = Vehicle::find()
			->with(['vehicleType' => function($q) {
					$q->select(['vehicle_type_id','vehicle_type']);
				}])
            ->select(['vehicle_type_id', 'COUNT(*) AS cnt'])
            //->where('vehicle_type_id != null')
            ->groupBy(['vehicle_type_id'])
            ->asArray()
            ->all();
            
            $Array = array();
			$Array[] = array('VehicleType','count');
            foreach($vehicle as $key => $value)
			{	
				//print_r($value['vehicleType']['vehicle_type']);
				$vtype = $value['vehicleType']['vehicle_type'];
				$vtype = $vtype ? $vtype : "Not Specified" ;
				$cnt = intval($value['cnt']);
				$Array[] = array($vtype,$cnt);
			}
			return $Array;
        
	}
	
	public function vehiclesByStockStatus()
	{	
		$results = Vehicle::find()
            ->joinwith(['stockStatus'])
			->select( 'vehicle.stock_status_id, vehicle.record_status, COUNT(*) AS count' )//,stockStatus.stock_status
			//->Where(['vehicle.record_status' => 'active'])
            ->groupBy ('stock_status_id')
			->asArray()
            ->all();
            
		$data[] = array('Status','Vehicle Count');
		foreach($results as $key => $value) {
			//if ($value['record_status'] == 'active')
		   $data[] = array($value['stockStatus']['stock_status'],intval($value['count']));
		   
		}
		  
		//var_dump($data);
		return $data;
	}
	
	public function salesPerMonth()
	{	
		$query1 = \Yii::$app->db->createCommand('
		SELECT DISTINCT DATE_FORMAT(sales_date, "%m-%Y") as m_date, sales_date, SUM(final_sales_amount) AS total
		FROM sales
		GROUP BY MONTH(m_date)')->queryAll();//WHERE YEAR(sales_date) = YEAR(CURDATE())

		$query = Sales::find()
			->select('MONTH(sales_date) AS month, YEAR(sales_date) AS year, sales_date, SUM(final_sales_amount) AS total, COUNT(*) AS total_sold' )
			//->select('MONTH(sales_date) AS month, DATE_FORMAT(sales_date, "%M %Y") m_date, sales_date, SUM(final_sales_amount) AS total, COUNT(*) AS total_sold' )
			->andFilterWhere(['void' => ''])
			//->where (['year => YEAR(CURDATE()'])
			->andFilterWhere(['void' => ''])
			->andFilterWhere(['BETWEEN', 'sales_date',
                new \yii\db\Expression('(NOW() - INTERVAL 12 MONTH)'),
                new \yii\db\Expression('NOW()')
            ])
            ->groupBy ('year, month')
			->asArray()
            ->all();
            
		$months = array();
		$total = array();
		for ($i = 0; $i < sizeof($query); $i++) {
			$date = $query[$i]["sales_date"];
			$months[] = date('M-Y', strtotime($date)); //date("F", mktime(0, 0, 0, $y, 10)).$query[$i]["year"];
			$total[] = (int) $query[$i]["total_sold"];
		}
		return array('months' => $months, 'total' => $total);
	}
	
	public function salesPerDay()
	{	
		$query = \Yii::$app->db->createCommand('
		SELECT DISTINCT MONTH(sales_date) as month, DATE_FORMAT(sales_date, "%D %M %Y") date, sales_date, SUM(final_sales_amount) AS total, COUNT(*) AS total_sold
		FROM sales
		WHERE YEAR(sales_date) = YEAR(CURDATE())
		AND (sales_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY))
		GROUP BY MONTH(sales_date)')->queryAll();
		//DATE_FORMAT(return_date, '%d %M %Y') returned
		
		$salesdays = array();
		$total = array();
		for ($i = 0; $i < sizeof($query); $i++) {
			//$y = $query[$i]["month"];
			$y = $query[$i]["sales_date"];
			$salesdays[] = $y; 
			$total[] = (int) $query[$i]["total_sold"];
		}
		
		return array('salesdays' => $salesdays, 'total' => $total);
	}
	
	public function salesCountByType() 
	{
		
			$month_offset = 4;
			$date = date("Y-m-d");
			$current_month = date_parse($date)['month'];
			$current_year = date_parse($date)['year']; 
			
			$sales = Sales::find()
            ->joinwith(['vehicle'])
			->select( 'vehicle.vehicle_id,vehicle.vehicle_type_id, MONTH(sales_date) AS m_date, sales_date AS date, SUM(final_sales_amount) AS Total, COUNT(*) AS Sold' )
			//->andFilterWhere(['void' => ''])
			->andFilterWhere(['BETWEEN', 'sales_date',
                new \yii\db\Expression('(NOW() - INTERVAL 4 MONTH)'),
                new \yii\db\Expression('NOW()')
            ])
            ->groupBy ('vehicle_type_id,m_date')
			->asArray()
            ->all();
            
            $vehicleType = Vehicle::find()
            ->joinwith('vehicleType')
            ->select( 'vehicle.vehicle_type_id, vehicle_type' )
            ->asArray()
            ->all();
			
			$i = 0;
			$data = array();
			$months = array();
			$monthInt = intval(date_parse($date)['month']);
			//array_flip
			for ($x = $monthInt - $month_offset; $x < $monthInt + 1 ; $x++){
				$m = date("F", mktime(0, 0, 0, $x, 10));
				$monthsArray[$i] = $m;
				$monthsIndx[$x] = $i;
				$data[$i] =  0;
				$i++;
			}
			//var_dump($sales);
				
			$array = array();
			$cartype_arr = array();
			$monthIndx = 0;
			$x = 0;
			foreach($sales as $key => $value)
			{
				foreach($vehicleType as $key1 => $value1) {
					if ($value1['vehicle_type_id'] == $value['vehicle_type_id'])
					$cartype = $value1['vehicle_type'] ? $value1['vehicle_type'] : 'Others';
				}
					$num_sold = intval($value['Sold']); 
					$date = $value['date']; 
					$mIndx = intval(date_parse($date)['month']);
					$monthIndx = $monthsIndx[$mIndx];
					$mdate = date('F-Y', strtotime($date));
						
					if ($monthIndx >= 0) {
						if (!array_key_exists($cartype,$cartype_arr)){//
							$array[$x]['type'] = 'column';
							$array[$x]['name'] = $cartype;
							$array[$x]['data'] = $data;							
							$cartype_arr[$cartype]= $x;	
							$array[$x]['data'][$monthIndx] = $num_sold;
							$x++;						
						} else {
							//if indx is where cartype as qualified above
							$indx = $cartype_arr[$cartype];
							$i = count($array[$indx]['data']);
							if (isset($array[$indx]['data'][$monthIndx])){
								$array[$indx]['data'][$monthIndx] = $array[$indx]['data'][$monthIndx] + $num_sold;
							}else{
								$array[$indx]['data'][$monthIndx] = $num_sold;
							}
							
						}
					}
			}
			return array('monthsArray' => $monthsArray, 'results' => $array);
	}
		
}
