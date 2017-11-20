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
                  where a.Date <= NOW() and a.Date >= Date_add(Now(),interval - 2 month)
                  group by md  ) t1                  
                left join
                  (SELECT DATE_FORMAT(pd_create_date, '%b') AS month, 
                            IFNULL(SUM(pd_poyals_added) ,0) as accrued ,
                            IFNULL(SUM(pd_poyals_redeemed),0) as redeemed ,
                             MONTHNAME(STR_TO_DATE(DATE_FORMAT(pd_create_date, '%m'), '%m')) as report_month  ,         
                            DATE_FORMAT(pd_create_date, '%m-%Y') as md
                  FROM pty_cust_poyalty_card_merchant_dtl
                  where pd_create_date <= NOW() and pd_create_date >= Date_add(Now(),interval - 3 month) and
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

      Log::debug('cashpoints_daily   : ' . count($cashpoints_daily));   

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


       // Log::info(DB::getQueryLog());   
                     
          if (isset($cashpoints_collec[0]) ) // current month 
          {
          $cashpoints1_records = (array) $cashpoints_collec[0];
          $cashpoints_array [0] =  array(   "month" =>  $cashpoints1_records ['month'], 
          "accrued"=> $cashpoints1_records ['accrued'],
          "redeemed"=> $cashpoints1_records ['redeemed']   ); 
          }
          else {

          $cashpoints_array [0] =  array( "month" =>  $currentMonth, 
          "accrued"=> 0,
          "redeemed"=> 0 ); 
          }


          if (isset($cashpoints_collec[1]) ) // last month 
          {
          $cashpoints2_records = (array) $cashpoints_collec[1]; 
          $cashpoints_array [1] =  array( "month" =>  $cashpoints2_records ['month'], 
          "accrued"=> $cashpoints2_records ['accrued'],
          "redeemed"=> $cashpoints2_records ['redeemed']   );             }
          else {

          $cashpoints_array [1] =  array( "month" =>  $lastMonth, 
          "accrued" => 0,
          "redeemed" => 0 ); 
          }

          if (isset($cashpoints_collec[2]) ) // previous month 
          {
          $cashpoints3_records = (array) $cashpoints_collec[2];


          $cashpoints_array [2] =  array( "month" =>  $cashpoints3_records ['month'], 
          "accrued"=> $cashpoints3_records ['accrued'],
          "redeemed"=> $cashpoints3_records ['redeemed']   );

          }

          else  {

          $cashpoints_array [2] =  array( "month" =>  $prevMonth, 
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
        ->join('pty_cust_merchant_bill','pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')     
        //no
         ->where([
          ['pd_merchant_id' ,'=',$merchant_id ],
          [DB::raw('month(pd_merchant_bill_date)'),'=' , $report_date->format('m') ]
        ])
       // ->whereRaw('pd_merchant_id = ?  and month(pd_bill_date) = month(?)') 
        ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as TransId'  , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as TypeAR'  , 
        'pty_cust_poyalty_card_merchant_dtl.pd_create_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as CustomerMobile' ,
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber' ,
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount',
        'pty_cust_poyalty_card_merchant_dtl.pd_poyals_added as accrued',
        'pty_cust_poyalty_card_merchant_dtl.pd_poyals_redeemed as redeemed'
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
    ->leftjoin('pty_cust_merchant_bill','pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No','=','pty_cust_merchant_bill.mb_merchant_bill_No')
      ->where([
          ['pd_merchant_id' ,'=',$merchant_id ],
          ['pd_merchant_bill_date','=' , $report_date->format('Y-m-d') ]
      ])     
       ->select(
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_id as TransId' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_transaction_type as TypeAR' , 
        'pty_cust_poyalty_card_merchant_dtl.pd_create_date as Date',
        'pty_cust_master.cm_Name as Name',
        'pty_cust_master.cm_mobile_no as CustomerMobile',
        'pty_cust_master.cm_town as Town',
        'pty_cust_poyalty_card_merchant_dtl.pd_merchant_bill_No as BillNumber',
        'pty_cust_merchant_bill.mb_bill_amount as BillAmount',
        'pty_cust_merchant_bill.mb_final_amount-paid as FinalAmount',
        'pty_cust_poyalty_card_merchant_dtl.pd_poyals_added as accrued',
        'pty_cust_poyalty_card_merchant_dtl.pd_poyals_redeemed as redeemed'

     )            
      ->get();
      Log::info(DB::getQueryLog());
      Log::debug('cashpoints_daily   : ' . count($cashpoints_daily));  

        return  $cashpoints_daily;

}

public function generatePDF(Fpdf $pdf){

$textColour = array( 0, 0, 0 );
$headerColour = array( 100, 100, 100 );
$tableHeaderTopTextColour = array( 255, 255, 255 );
$tableHeaderTopFillColour = array( 125, 152, 179 );
$tableHeaderTopProductTextColour = array( 0, 0, 0 );
$tableHeaderTopProductFillColour = array( 143, 173, 204 );
$tableHeaderLeftTextColour = array( 99, 42, 57 );
$tableHeaderLeftFillColour = array( 184, 207, 229 );
$tableBorderColour = array( 50, 50, 50 );
$tableRowFillColour = array( 213, 170, 170 );
$reportName = "Cashpoints Report";
$reportNameYPos = 160;
$logoFile = "widget-company-logo.png";
$logoXPos = 50;
$logoYPos = 108;
$logoWidth = 110;
$columnLabels = array( "Date", "Trans-Id", "Customer Name" , "Mobile","Bill Number" ,"Bill Amount","Paid Amount","Type A/R","Accrued" ,"Redeemed");
$rowLabels = array( "SupaWidget", "WonderWidget", "MegaWidget", "HyperWidget" );


$chartColours = array(
                  array( 255, 100, 100 ),
                  array( 100, 255, 100 ),
                  array( 100, 100, 255 ),
                  array( 255, 255, 100 ),
                );
/*
$data = array(
          array( 9940, 10100, 9490, 11730 ),
          array( 19310, 21140, 20560, 22590 ),
          array( 25110, 26260, 25210, 28370 ),
          array( 27650, 24550, 30040, 31980 ),
        ); 

*/
//  $data = $this->getDailyReport("10",DateTime::createFromFormat('Y-m-d','2017-10-15' )); 
//  $data = $this->getMonthlyReport("10",DateTime::createFromFormat('Y-m-d','2017-10-15' )); 




 // Log::info(var_dump($data)); 


    $pdf = new PDF( 'L', 'mm', 'A4' );

    $pdf->SetTextColor( $textColour[0], $textColour[1], $textColour[2] );
  //  $pdf->AddPage();

    // Logo
   // $pdf->Image( $logoFile, $logoXPos, $logoYPos, $logoWidth );

    // Report Name
 /*   $pdf->SetFont( 'Arial', 'B', 24 );
    $pdf->Ln( $reportNameYPos );
    $pdf->Cell( 0, 15, $reportName, 0, 0, 'C' );

*/
      /**
        Create the page header, main heading, and intro text
      **/
      $pdf->AliasNbPages(); 
      $pdf->AddPage();

    
      $pdf->SetTextColor( $headerColour[0], $headerColour[1], $headerColour[2] );
      $pdf->SetFont( 'Arial', '', 17 );
      $pdf->Cell( 0, 15, $reportName, 0, 0, 'C' );
      $pdf->SetTextColor( $textColour[0], $textColour[1], $textColour[2] );
     
      $pdf->SetFont( 'Arial', '', 10 );
     // $pdf->Cell(0, 5, "Page " . $pdf->PageNo() . "/{nb}", 0, 1);      
     // $pdf->Cell(0, 5, "Page " . $pdf->PageNo() . "/{totalPages}", 0, 1);
      /*
        $pdf->SetFont( 'Arial', '', 20 );
        $pdf->Write( 19, "2009 Was A Good Year" );
        $pdf->Ln( 16 );
        $pdf->SetFont( 'Arial', '', 12 );
        $pdf->Write( 6, "Despite the economic downturn, WidgetCo had a strong year. Sales of the HyperWidget in particular exceeded expectations. The fourth quarter was generally the best performing; this was most likely due to our increased ad spend in Q3." );
        $pdf->Ln( 12 );
        $pdf->Write( 6, "2010 is expected to see increased sales growth as we expand into other countries." );
   */

          /**
            Create the table
          **/

          $pdf->SetDrawColor( $tableBorderColour[0], $tableBorderColour[1], $tableBorderColour[2] );
          $pdf->Ln( 15 );

          // Create the table header row
          $pdf->SetFont( 'Arial', 'B', 10 );

          // "PRODUCT" cell   
        //  $pdf->SetTextColor( $tableHeaderTopProductTextColour[0],  $tableHeaderTopProductTextColour[1], $tableHeaderTopProductTextColour[2] );
           $pdf->SetTextColor( $tableHeaderTopTextColour[0], $tableHeaderTopTextColour[1], $tableHeaderTopTextColour[2] );
          $pdf->SetFillColor( $tableHeaderTopProductFillColour[0], $tableHeaderTopProductFillColour[1], $tableHeaderTopProductFillColour[2] );
        //  "Date", "Trans-Id", "Customer Name" , "Mobile","Bill Number" ,"Bill Amount","Paid Amount","Type A/R","Accrued" ,"Redeemed"
          $pdf->Cell( 12, 12, " S.NO", 1, 0, 'L', true );
          $pdf->Cell( 25, 12, " Date", 1, 0, 'C', true );
          $pdf->Cell( 25, 12, " Trans-Id", 1, 0, 'C', true );
          $pdf->Cell( 35, 12, " Customer Name", 1, 0, 'L', true );
          $pdf->Cell( 30, 12, " Mobile", 1, 0, 'C', true );
          $pdf->Cell( 25, 12, " Bill Number", 1, 0, 'L', true );
          $pdf->Cell( 25, 12, " Bill Amount", 1, 0, 'L', true );
          $pdf->Cell( 25, 12, " Paid Amount", 1, 0, 'L', true );
          $pdf->Cell( 25, 12, " Type A/R", 1, 0, 'C', true );
          $pdf->Cell( 25, 12, " Accrued", 1, 0, 'C', true );
          $pdf->Cell( 25, 12, " Redeemed", 1, 0, 'C', true );




          // Remaining header cells
          $pdf->SetTextColor( $tableHeaderTopTextColour[0], $tableHeaderTopTextColour[1], $tableHeaderTopTextColour[2] );
         //  $pdf->SetFillColor( $tableHeaderTopFillColour[0], $tableHeaderTopFillColour[1], $tableHeaderTopFillColour[2] );

          for ( $i=0; $i<count($columnLabels); $i++ ) {
         //   $pdf->Cell( 30, 12, $columnLabels[$i], 1, 0, 'C', true );
          }

          $pdf->Ln( 12 );

          // Create the table data rows

          $fill = false;
          $row = 0;

          foreach ( $data as $indx => $dataRow ) {

            // Create the left header cell
            $pdf->SetFont( 'Arial', 'B', 10 );
          //  $pdf->SetTextColor( $tableHeaderLeftTextColour[0], $tableHeaderLeftTextColour[1],     $tableHeaderLeftTextColour[2] );
              $pdf->SetTextColor( $textColour[0], $textColour[1], $textColour[2]);
              $pdf->SetFillColor( $tableHeaderLeftFillColour[0], $tableHeaderLeftFillColour[1], $tableHeaderLeftFillColour[2]);

            $pdf->Cell( 12, 12, $indx+1 , 1, 0, 'C', $fill );

            // Create the data cells
            $pdf->SetTextColor( $textColour[0], $textColour[1], $textColour[2] );
         //   $pdf->SetFillColor( $tableRowFillColour[0], $tableRowFillColour[1], $tableRowFillColour[2] );
            $pdf->SetFont( 'Arial', '', 10 );
        

        setlocale(LC_MONETARY, 'en_IN.UTF-8');
        $pdf->Cell( 25, 12,   $dataRow->Date  , 1, 0, 'C', $fill ); 
        $pdf->Cell( 25, 12,   $dataRow->TransId , 1, 0, 'C', $fill );   
        $pdf->Cell( 35, 12,   $dataRow->Name , 1, 0, 'C', $fill );
        $pdf->Cell( 30, 12,   $dataRow->CustomerMobile , 1, 0, 'C', $fill ); 
        $pdf->Cell( 25, 12,   $dataRow->BillNumber , 1, 0, 'C', $fill );  
        $pdf->Cell( 25, 12,  number_format($dataRow->BillAmount), 1, 0, 'C', $fill ); 
        $pdf->Cell( 25, 12,  number_format($dataRow->FinalAmount), 1, 0, 'C', $fill );
        $pdf->Cell( 25, 12,    $dataRow->TypeAR, 1, 0, 'C', $fill );
        $pdf->Cell( 25, 12,    $dataRow->accrued, 1, 0, 'C', $fill );
        $pdf->Cell( 25, 12,    $dataRow->redeemed, 1, 0, 'C', $fill );

        for ( $i=0; $i<count($columnLabels); $i++ ) {
        //  $pdf->Cell( 30, 12, ( '$' . number_format( $dataRow->FinalAmount ) ), 1, 0, 'C', $fill );
        }

        $row++;
        $fill = !$fill;
        $pdf->Ln( 12 );
      }

    //  $pdf->Cell(0, 5, "Page " . $pdf->PageNo() . "/{nb}", 0, 1);

    //  $pdf->PageNo();
        

    $pdf->Output( "report.pdf", "I" );




}


public function configurePDF(){

// Begin configuration

$textColour = array( 0, 0, 0 );
$headerColour = array( 100, 100, 100 );
$tableHeaderTopTextColour = array( 255, 255, 255 );
$tableHeaderTopFillColour = array( 125, 152, 179 );
$tableHeaderTopProductTextColour = array( 0, 0, 0 );
$tableHeaderTopProductFillColour = array( 143, 173, 204 );
$tableHeaderLeftTextColour = array( 99, 42, 57 );
$tableHeaderLeftFillColour = array( 184, 207, 229 );
$tableBorderColour = array( 50, 50, 50 );
$tableRowFillColour = array( 213, 170, 170 );
$reportName = "2009 Widget Sales Report";
$reportNameYPos = 160;
$logoFile = "widget-company-logo.png";
$logoXPos = 50;
$logoYPos = 108;
$logoWidth = 110;
$columnLabels = array( "Q1", "Q2", "Q3", "Q4" );
$rowLabels = array( "SupaWidget", "WonderWidget", "MegaWidget", "HyperWidget" );
$chartXPos = 20;
$chartYPos = 250;
$chartWidth = 160;
$chartHeight = 80;
$chartXLabel = "Product";
$chartYLabel = "2009 Sales";
$chartYStep = 20000;

$chartColours = array(
                  array( 255, 100, 100 ),
                  array( 100, 255, 100 ),
                  array( 100, 100, 255 ),
                  array( 255, 255, 100 ),
                );

$data = array(
          array( 9940, 10100, 9490, 11730 ),
          array( 19310, 21140, 20560, 22590 ),
          array( 25110, 26260, 25210, 28370 ),
          array( 27650, 24550, 30040, 31980 ),
        );

// End configuration

}


}


class PDF extends FPDF
{

  function init()
   {
         $this->AliasNbPages();
                
   }
   


//Page header
function Header()
{
    //Logo
    //$this->Image('logo_pb.png',10,8,33);
    //Arial bold 15
    $this->SetFont('Arial','B',15);
    //Move to the right
    $this->Cell(80);
    //Title
   // $this->Cell(30,10,'Cashpoints Report',1,0,'C');
    //Line break
    $this->Ln(20);
}

//Page footer
function Footer()
{
    //Position at 1.5 cm from bottom
    $this->SetY(-15);
    //Arial italic 8
    $this->SetFont('Arial','I',8);
    //Page number
    $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
}
}
