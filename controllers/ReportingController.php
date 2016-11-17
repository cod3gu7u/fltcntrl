<?php

namespace frontend\controllers;

use Yii;
use app\models\Reporting;
use app\models\PaymentMethod;
use app\models\PaymentVoucher;
use app\models\SalesTransaction;
use app\models\Vehicle;
use app\models\VehicleMake;
use app\models\Sales;
use app\models\Bank;
use app\models\BankTransfer;
use app\models\BankBalance;
use yii\db\Expression;



class ReportingController extends \yii\web\Controller
{
    public function actionIndex()
    {
        return $this->render('index');
    }

	public function actionInputForm($controller_path = null)
	{
	    $model = new Reporting();

	    if ($model->load($postdat = Yii::$app->request->post())) {
	        if ($model->validate()) {
	        	$params = [
		        	'start_date' => $model->start_date, 
		        	'end_date' => $model->end_date,
		        	'parameter' => $model->param,
		        	];
	        	return $this->redirect([$controller_path, 'params' => $params]);
	        }
	    }

	    return $this->render('input-form', [
	        'controller_path' => $controller_path,
	        'model' => $model,
	    ]);
	}

	public function actionDashboard()
    {

		\Yii::$app->formatter->locale = 'mn-CN'; //language code like for english --> en-Us

		$vehiclebymake =  $this->vehiclebymake();
		$vehiclebytype = $this->Vehiclebytype();
		$salesPerDay = $this->salesPerDay();
		$salesPerMonth = $this->salesPerMonth();
		$salesCountByType_data = $this->salesCountByType();
		$salesCountByType = $salesCountByType_data['results'];
		$monthsArray = $salesCountByType_data['monthsArray'];
		$vehiclesByStockStatus = $this->vehiclesByStockStatus();
		$salesPerDay = $this->salesPerDay();
		$salesCountByType_data = $this->salesCountByType();

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
       
        return $this->render('dashboard',[
			'make' => $vehiclebymake,
			'type' => $vehiclebytype, 
			//'sales' => $sales,
			'salesByType' => $salesCountByType,
			'monthsArray' => $monthsArray,
			'salesPerDay' => $salesPerDay,
			'salesPerMonth' => $salesPerMonth,
			'vehiclesByStockStatus' => $vehiclesByStockStatus,
			'salesTransanctionByType' => SalesTransactionController::salesTransanctionByType(),
			'salesTransactionTypeList' => SalesTransactionController::salesTransactionTypeList(),
			'vehiclesCountByStatus' => VehicleController::vehiclesCountByStatus(),
			'salesSettled' => SalesController::salesSettled(),
			
		]);
    }

    // Customers
    public function actionAllCustomers()
    {
        return $this->redirect(['/sales/list-report']);
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

	public function actionDayEndReport(array $params = array())
	{
        $title = 'Daily Report: ';
        $htmlContent .= $this->renderPartial('@app/views/report/_header');
                
        if(isset($params['start_date'])){
            $start_date = $params['start_date'];
            $end_date = isset($params['end_date']) ? $params['end_date'] : $start_date;
            $title .= $start_date . ' - ' . $end_date;
	

        /*************************************************************
			// Daily sales group by payment method (cash, cheque, eft)
		**************************************************************/
			// get payment methods
			$payment_methods = SalesTransaction::find()
	                ->where(['between', 'transaction_date', $start_date, $end_date])
	                ->select('DISTINCT(payment_method_id) as payment_method_id')
	                ->all();

            $payment_currencies = SalesTransaction::find()
	                ->where(['between', 'transaction_date', $start_date, $end_date])
	                ->select('DISTINCT(currency_id) as currency_id')
	                ->all();


	        $htmlContent .= $this->renderPartial('@app/views/sales-transaction/_index-header', [
			  'title' => $title,]);

		 	foreach ($payment_methods as $payment_method => $pm_value ) {

		        foreach ($payment_currencies as $payment_currency => $pc_value) {
		            $sales_trnx_model = SalesTransaction::find()
		                ->where(['between', 'transaction_date', $start_date, $end_date])
		                ->andWhere(['payment_method_id' => $pm_value->payment_method_id])
		                ->andWhere(['currency_id' => $pc_value->currency_id])
		                ->all();

			        $subtitle = ' : ' . $pm_value->paymentMethod->payment_method . '<br/>Currency : ' . $pc_value->currency->currency_long;

	                $dataProvider = new \yii\data\ArrayDataProvider([
	                      'allModels' => $sales_trnx_model,
	                      'pagination' => [
	                          'pageSize' => 80,
	                      ],
	                  ]);

	                $htmlContent .= $this->renderPartial('@app/views/sales-transaction/_index', [
	                      'dataProvider' => $dataProvider,
	                      'title' => $title,
	                      'subtitle' => $subtitle,
	                  ]);
	            }     
	            $htmlContent .= "<pagebreak />";
	        }

        /*************************************************************
		// Daily payments, group by payment method (cash, cheque, eft)
		**************************************************************/
		// get banks
		$banks_model = Bank::find()
				->where(['record_status' => Bank::ACTIVE])
				->all();

		$payment_methods = PaymentVoucher::find()
	                ->where(['between', 'pv_date', $start_date, $end_date])
	                ->select('DISTINCT(payment_method_id) as payment_method_id')
	                ->all();

        $htmlContent .= $this->renderPartial('@app/views/payment-voucher/_index-header', [
			  'title' => $title,]);

		 	foreach ($banks_model as $bank) {
		 		// foreach ($payment_methods as $payment_method => $pm_value ) {
	             
		            $payment_voucher_model = PaymentVoucher::find()
		                ->where(['between', 'pv_date', $start_date, $end_date])
		                ->andWhere(['bank_id' => $bank->bank_id])
		                // ->andWhere(['payment_method_id' => $pm_value->payment_method_id])
		                ->all();

			        $subtitle = 'Payments : ' . $bank->bank;
			        // $subtitle .= '<br/>Payment Method : ' . $pm_value->paymentMethod->payment_method;

		            $dataProvider = new \yii\data\ArrayDataProvider([
		                  'allModels' => $payment_voucher_model,
		                  'pagination' => [
		                      'pageSize' => 80,
		                  ],
		              ]);

		            $htmlContent .= $this->renderPartial('@app/views/payment-voucher/_index', [
		                  'dataProvider' => $dataProvider,
		                  'title' => $title,
		                  'subtitle' => $subtitle,
		              ]);
		        // }
	        }
            $htmlContent .= "<pagebreak />";
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        

        /*************************************************************
		// Daily transfers (cash, cheque, eft)
		**************************************************************/
		$bank_transfer_model = BankTransfer::find()
	                ->where(['between', 'transfer_date', $start_date, $end_date])
	                ->all();

        $subtitle = 'Transfers : ' . $start_date . ' - ' . $end_date;

        $dataProvider = new \yii\data\ArrayDataProvider([
              'allModels' => $bank_transfer_model,
              'pagination' => [
                  'pageSize' => 80,
              ],
          ]);

        $htmlContent .= $this->renderPartial('@app/views/bank-transfer/_index', [
              'dataProvider' => $dataProvider,
              'title' => $title,
              'subtitle' => $subtitle,
          ]);

        $htmlContent .= "<pagebreak />";

        /*************************************************************
		// Daily summary
		**************************************************************/
		$title = 'Daily Summary';
        $subtitle = 'Summary : ' . $start_date . ' - ' . $end_date;

        $htmlContent .= $this->renderPartial('_daily-summary-header', [
			  'title' => $title,
              'subtitle' => $subtitle,]);

		// Opening balances
		$bank_balance = BankBalance::find()
						// ->where(['between', 'start_date', $start_date, $end_date])
						// ->select([new Expression('SUM(opening_balance) as opening_balance'), 'bank_id'])
						->groupBy(['bank_id', 'start_date'])
						->having(new Expression('max(start_date)'))
	               		->all();

	 	foreach ($bank_balance as $bank) {

			// Daily Receipts
		    $sales_trnx_model = SalesTransaction::find()
		                ->where(['between', 'transaction_date', $start_date, $end_date])
		                ->andWhere(['payment_method_id' => SalesTransaction::SALES_RECEIPT_TRANSACTION_TYPE_ID])
		                ->andWhere(['bank_id' => $bank->bank_id])
						->groupBy(['bank_id'])
		                ->select([new Expression('SUM(total_amount) as total_amount')])
		                ->one();

			// Daily Payments
		    $payment_voucher_model = PaymentVoucher::find()
		                ->where(['between', 'pv_date', $start_date, $end_date])
		                ->andWhere(['bank_id' => $bank->bank_id])
		                ->groupBy(['bank_id'])
		                ->select([new Expression('SUM(final_amount) as final_amount')])
		                ->one();

			// Daily Transfer (Payment)
		    $bank_out_transfer_model = BankTransfer::find()
		                ->where(['between', 'transfer_date', $start_date, $end_date])
		                ->andWhere(['source_bank_id' => $bank->bank_id])
		                ->groupBy(['source_bank_id'])
		                ->select([new Expression('SUM(amount) as amount')])
		                ->one();

			// Daily Transfer (Deposit)
		    $bank_in_transfer_model = BankTransfer::find()
		                ->where(['between', 'transfer_date', $start_date, $end_date])
		                ->andWhere(['destination_bank_id' => $bank->bank_id])
		                ->groupBy(['destination_bank_id'])
		                ->select([new Expression('SUM(amount) as amount')])
		                ->one();

			// Daily Return
		    $return_trnx_model = SalesTransaction::find()
		                ->where(['between', 'transaction_date', $start_date, $end_date])
		                ->andWhere(['sales_transaction_type_id' => SalesTransaction::RETURN_TRANSACTION_TYPE])
		                ->andWhere(['bank_id' => $bank->bank_id])
						->groupBy(['bank_id'])
		                ->select([new Expression('SUM(total_amount) as total_amount')])
		                ->one();

		    // Compute total debits and credits
		    $total_debits = $bank->bank->bankBalance->opening_balance + $sales_trnx_model->total_amount + $bank_in_transfer_model->amount;
		    $total_credits = $payment_voucher_model->final_amount + $bank_out_transfer_model->amount + $return_trnx_model->total_amount;

		    // Putting it all together
	        $htmlContent .= $this->renderPartial('_daily-summary', [
	              'bank' => $bank->bank->bank,
	              'opening_balance' => $bank->bank->bankBalance->opening_balance,
	              'bank_currency' => $bank->bank->currency->currency_short,
	              'sales_trnx_model' => $sales_trnx_model->total_amount,
	              'payment_voucher_model' => $payment_voucher_model->final_amount,
	              'bank_in_transfer_model' => $bank_in_transfer_model->amount,
	              'bank_out_transfer_model' => $bank_out_transfer_model->amount,
	              'return_trnx_model' => $return_trnx_model->total_amount,
	              'total_debits' => $total_debits,
	              'total_credits' => $total_credits,
	          ]);
		}

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        $pdf = Yii::$app->pdf;
        $pdf->methods = [ 'SetHeader'=>['Daily Report| |Fleet-21 Report|'],
            'SetFooter'=>['Daily Report|{PAGENO}|{DATE j-F-Y}'],
        ];
        $pdf->content = $htmlContent;
        $pdf->options =  [
                // 'orientation' => \kartik\mpdf\Pdf::ORIENT_LANDSCAPE,
                // 'title' => 'Sales Transaction Report No.' . $id,
                // 'subject' => 'Fleet 21 Sales Transaction Report No.' . $id,
                'output' => 'fleet21_day_end_report.pdf',
                'keywords' => 'skymouse fleet-21 end of day',
                'author' => 'SKYMOUSE Fleet-21',
                'creator' => 'SKYMOUSE Fleet-21'];
        return $pdf->render();
	}

}
