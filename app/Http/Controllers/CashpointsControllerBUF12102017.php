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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

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
    
      Log::info('Input merchant_id ' . $merchant_id);

      $cashpoints_collec =  DB::select(
          "select SUM(pd_poyals_added) as accrued , 
                   SUM(pd_poyals_redeemed) as redeemed ,
                    MONTHNAME(STR_TO_DATE(DATE_FORMAT(pd_create_date, '%m'), '%m')) as report_month    
                   from `pty_cust_poyalty_card_merchant_dtl`
                   where 
                   (`pd_merchant_id` = ?)
                   AND DATE_FORMAT(pd_create_date, '%m') > DATE_FORMAT(current_date(), '%m') -3       
                   GROUP BY DATE_FORMAT(pd_create_date, '%m')", [$merchant_id]);

      $cashpoints_daily = DB::table ('pty_cust_poyalty_card_merchant_dtl')
     
    ->join('pty_cust_master', 'pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_master.cm_cust_id')
    ->join('pty_cust_merchant_bill','pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')
      ->where([
          ['pd_merchant_id' ,'=',$merchant_id ],
          ['pd_create_date','=' , DB::raw('CURDATE()') ]
      ])     
       ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as Trans id' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as Type A/R' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_create_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as Customer Mobile',
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber',
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount'
     )            
      ->get();

      Log::info('cashpoints_daily   : ' . count($cashpoints_daily));   


      $cashpoints_monthly = DB::table ('pty_cust_poyalty_card_merchant_dtl')
       ->join('pty_cust_master', 'pty_cust_poyalty_card_merchant_dtl.pd_cust_id', '=', 'pty_cust_master.cm_cust_id')
        ->join('pty_cust_merchant_bill','pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')
        ->where([
        ['pd_merchant_id' ,'=',$merchant_id ]

        ])     
        ->whereRaw('month(pd_create_date) = month(CURDATE())') 
        ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as Trans id' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as Type A/R' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_create_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as Customer Mobile',
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber',
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount'
     )              
        ->get();

      Log::info('cashpoints_monthly   : ' .count($cashpoints_monthly));  


      //  Log::info(DB::getQueryLog());   


         if (isset($cashpoints_collec[0]) )
         {
             $cashpoints1_records = (array) $cashpoints_collec[0];  
             $cashpoints2_records = (array) $cashpoints_collec[1];
             $cashpoints3_records = (array) $cashpoints_collec[2];


           


         $cashpoints_array [0] =  array( "month" =>  $cashpoints1_records ['report_month'], 
                                          "accrued"=> $cashpoints1_records ['accrued'],
                                    "redeemed"=> $cashpoints1_records ['redeemed']   );

         $cashpoints_array [1] =  array( "month" =>  $cashpoints2_records ['report_month'], 
                                          "accrued"=> $cashpoints2_records ['accrued'],
                                    "redeemed"=> $cashpoints2_records ['redeemed']   );

          $cashpoints_array [2] =  array( "month" =>  $cashpoints3_records ['report_month'], 
                                          "accrued"=> $cashpoints3_records ['accrued'],
                                    "redeemed"=> $cashpoints3_records ['redeemed']   );
         

        return Response::json(array('success'=> true , 'cashpoints'=>  $cashpoints_array ,'cashpoints_daily' =>
          $cashpoints_daily,'cashpoints_monthly'=>$cashpoints_monthly ));


         }         
         else {

         return Response::json(array('success'=> true , 
          'cashpoints'=>  array(),
          'cashpoints_daily' =>  $cashpoints_daily,
          'cashpoints_monthly'=>$cashpoints_monthly ));
      }
                             
    }


    public function get_view($id)
{
   if (is_numeric($id))
   {
       $authorModel = Authors::find($id);
   }
   else
   {
       $column = 'name'; // This is the name of the column you wish to search

       $authorModel = Authors::where($column , '=', $id)->first();
   }

   return View::make('authors.view')
                ->with('author', $authorModel)
                ->with('title', $authorModel->name);

}
 


}