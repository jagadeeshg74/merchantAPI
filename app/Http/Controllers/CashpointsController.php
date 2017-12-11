<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers;

use App\Models\Redeem ;
use App\Models\RedeemDetail ;
use App\Models\Customer ;
use App\Models\PoyaltyCard;

use Response;
use Log;
use Exception;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use DateTime;
use App\Providers\Codedge\Fpdf\Fpdf\Fpdf;

define("REPORT_DAILY", "daily", true);
define("REPORT_MONTHLY", "monthly", true);
 

class CashpointsController extends Controller
{
    //
		public function __Construct(){

			
		}


    public function index(Request $request){

      return Response::json(array('success' => true));
    }

		 public function store(Request $request)
	 {

	 	

    }
        /***
            find customers based on mobile nos


        ****/

     public function showMerchantCashpoints($merchant_id)
    {

      DB::enableQueryLog();
    
      Log::info('Cashpoints merchant_id ' . $merchant_id);

      $cashpoints_collec =  DB::select(
          "Select t1.month ,IFNULL(t2.accrued  ,0) as accrued ,
                IFNULL(t2.redeemed  ,0) as redeemed 
                from 
                (select DATE_FORMAT(a.Date,'%b') as month,
                  DATE_FORMAT(a.Date, '%m-%Y') as md,
                  '0' as  amount
                  from (
                    select curdate() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as Date
                    from (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as a
                    cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as b
                    cross join (select 0 as a union all select 1 union all select 2 union all select 3 union all select 4 union all select 5 union all select 6 union all select 7 union all select 8 union all select 9) as c
                  ) a
                  where a.Date <= NOW() and a.Date >= Date_add(Now(),interval - 4 month)
                  group by md  ) t1                  
                left join
                  (SELECT DATE_FORMAT(pd_merchant_bill_date, '%b') AS month, 
                            IFNULL(SUM(pd_poyals_added) ,0) as accrued ,
                            IFNULL(SUM(pd_poyals_redeemed),0) as redeemed ,
                             MONTHNAME(STR_TO_DATE(DATE_FORMAT(pd_merchant_bill_date, '%m'), '%m')) as report_month  ,         
                            DATE_FORMAT(pd_merchant_bill_date, '%m-%Y') as md
                  FROM pty_cust_poyalty_card_merchant_dtl
                  where pd_merchant_bill_date <= NOW() and pd_merchant_bill_date >= Date_add(Now(),interval - 4 month) and
                  (`pd_merchant_id` = ?)
                  GROUP BY md) t2 
                  on t2.md = t1.md 
                group by t1.md
                order by t1.md", [$merchant_id]);

      Log::info(DB::getQueryLog());   
      Log::debug('cashpoints_daily   : ' . count($cashpoints_collec ));   

      // Daily Report ...........................

      $cashpoints_daily = DB::table ('pty_cust_poyalty_card_merchant_dtl')
     
    ->join('pty_cust_master', 'pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_master.cm_cust_id')
    ->join('pty_cust_merchant_bill','pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')
      ->where([
          ['pd_merchant_id' ,'=',$merchant_id ],
          ['pd_merchant_bill_date','=' , DB::raw('CURDATE()') ]
      ])     
       ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as Trans id' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as Type A/R' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as Customer Mobile',
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber',
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount'
     )            
      ->get();

      Log::debug('cashpoints_daily   : ' . count($cashpoints_daily));   

      $cashpoints_monthly = DB::table ('pty_cust_poyalty_card_merchant_dtl')
       ->join('pty_cust_master', 'pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_master.cm_cust_id')
        ->join('pty_cust_merchant_bill','pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')
        ->where([
        ['pd_merchant_id' ,'=',$merchant_id ]

        ])     
        ->whereRaw('month(pd_merchant_bill_date) = month(CURDATE())') 
        ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as Trans id' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as Type A/R' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as Customer Mobile',
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber',
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount'
     )              
        ->get();

      Log::info('cashpoints_monthly   : ' .count($cashpoints_monthly));  


       // Log::info(DB::getQueryLog());   

      $array_length = count($cashpoints_collec,COUNT_NORMAL);

      Log::info("Record count".count($cashpoints_collec,COUNT_NORMAL));
                     
          if (isset($cashpoints_collec[$array_length-1]) ) // current month 
          {
              $cashpoints1_records = (array) $cashpoints_collec[$array_length-1];
              $cashpoints_array [2] =  array(   "month" =>  $cashpoints1_records ['month'], 
              "accrued"=> $cashpoints1_records ['accrued'],
              "redeemed"=> $cashpoints1_records ['redeemed'] ); 
          }
          else {

          $cashpoints_array [2] =  array( "month" =>  0, 
          "accrued"=> 0,
          "redeemed"=> 0 ); 
          }


          if (isset($cashpoints_collec[$array_length-2]) ) // last month 
          {
          $cashpoints2_records = (array) $cashpoints_collec[$array_length-2]; 
          $cashpoints_array [1] =  array( "month" =>  $cashpoints2_records ['month'], 
          "accrued"=> $cashpoints2_records ['accrued'],
          "redeemed"=> $cashpoints2_records ['redeemed']   );             }
          else {

          $cashpoints_array [1] =  array( "month" =>  0, 
          "accrued" => 0,
          "redeemed" => 0 ); 
          }

          if (isset($cashpoints_collec[$array_length-3]) ) // previous month 
          {
          $cashpoints3_records = (array) $cashpoints_collec[$array_length-3];
          $cashpoints_array [0] =  array( "month" =>  $cashpoints3_records ['month'], 
          "accrued"=> $cashpoints3_records ['accrued'],
          "redeemed"=> $cashpoints3_records ['redeemed']   );

          }

          else  {

          $cashpoints_array [0] =  array( "month" =>  0, 
          "accrued"=> 0,
          "redeemed"=> 0 ); 


          }

  

        return Response::json(array('success'=> true , 

          'cashpoints'=>  $cashpoints_array ,
          'cashpoints_daily' => $cashpoints_daily,
          'cashpoints_monthly'=>$cashpoints_monthly ));


        
                             
    }



public function getReportData($merchant_id,$report_type, $report_date_input){

  Log::info ('Merchant Id ' . $merchant_id);
  Log::info ('Report Type ' . $report_type);
  Log::info ('Report Date ' . $report_date_input);

        $cashpoints_array   = [] ;
        $cashpoints_daily   = [];
        $cashpoints_monthly = [];

  // ........Validate the request 

  if (! is_null($report_date_input )) {

       $report_date = DateTime::createFromFormat('Y-m-d',$report_date_input );
       $errors = DateTime::getLastErrors();

       if (!empty($errors['warning_count'])){
          Log::info('Date format error : Strictly speaking, that report date is invalid!');
          throw new Exception ('Invalid Report Date : '.$report_date .' . Please check the report date .') ;
        }
  
  }


    if ((strcasecmp($report_type, REPORT_DAILY) == 0) || (strcasecmp($report_type, REPORT_MONTHLY) == 0) ) {
    
}
else {

    throw new Exception ('Invalid Report Type :'.$report_type) ;

}
  
  // get the data ...


switch ($report_type) {
    case REPORT_DAILY : $cashpoints_daily = $this-> getDailyReport($merchant_id ,$report_date);      
        break;
    case REPORT_MONTHLY:
         Log::info ('Running Monthly report for    : ' .  $merchant_id .'------' .$report_date->format('Y-m-d H:i:s'));  
        

         $cashpoints_monthly = $this -> getMonthlyReport($merchant_id ,$report_date);
        break;
   
        break;
    default:
        Log::info("Invalid Report Type .");
}

 return Response::json(array('success'=> true , 

          'cashpoints'=>  $cashpoints_array ,
          'cashpoints_daily' => $cashpoints_daily,
          'cashpoints_monthly'=>$cashpoints_monthly ));



}

public function getMonthlyReport($merchant_id , $report_date)
{
    // ------      monthly    -----------
     $cashpoints_monthly = DB::table ('pty_cust_poyalty_card_merchant_dtl')
        ->join('pty_cust_master', 'pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_master.cm_cust_id')
      ->leftjoin('pty_cust_merchant_bill', function($join)
        {
            $join->on('pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')
            ->on('pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_merchant_bill.mb_cust_id');
        })      
        //no
         ->where([
          ['pd_merchant_id' ,'=',$merchant_id ],
          [DB::raw('month(pd_merchant_bill_date)'),'=' , $report_date->format('m') ]
        ])
       // ->whereRaw('pd_merchant_id = ?  and month(pd_bill_date) = month(?)') 
        ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as TransId'  , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as TypeAR'  , 
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as CustomerMobile' ,
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber' ,
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount',
        'pty_cust_poyalty_card_merchant_dtl.pd_poyals_added  as accrued',
         'pty_cust_poyalty_card_merchant_dtl.pd_poyals_redeemed  as redeemed'
     )              
        ->get();

        Log::debug('cashpoints_monthly   : ' . count($cashpoints_monthly )); 

        return  $cashpoints_monthly;

}


public function getDailyReport($merchant_id , $report_date)
{
  // Daily Report ...........................

  DB::enableQueryLog();

      $cashpoints_daily = DB::table ('pty_cust_poyalty_card_merchant_dtl')
     
    ->leftjoin('pty_cust_master', 'pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_master.cm_cust_id')
    ->leftjoin('pty_cust_merchant_bill', function($join)
        {
            $join->on('pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')
            ->on('pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_merchant_bill.mb_cust_id');
        })  
      ->where([
          ['pd_merchant_id' ,'=',$merchant_id ],
          ['pd_merchant_bill_date','=' , $report_date->format('Y-m-d') ]
      ])     
       ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as TransId' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as TypeAR' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as CustomerMobile',
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber',
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount',
        'pty_cust_poyalty_card_merchant_dtl.pd_poyals_added  as accrued',
        'pty_cust_poyalty_card_merchant_dtl.pd_poyals_redeemed as redeemed'

     )            
      ->get();
      Log::info(DB::getQueryLog());
      Log::debug('cashpoints_daily   : ' . count($cashpoints_daily));  

        return  $cashpoints_daily;

}


}



