<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
    	Dear {{$CustomerName}},<br>
<br />
<p>
Thanks for using poyalty. You have {{$TransType}} {{$Cashpoints}} at the restaurant below and your <br /> current balance is {{$BalanceCashpoints}}.</p><br />
Restaurant  : {{$MerchantName}}<br />
Address     : {{$MerchantAddress}} .<br/>
Date        : {{date("M d,Y")}}<br />
Time        : {{date("H:i:sa")}}<br />
<br/>
your poyalty team <br /> 
India <br />
+917358498964<br />
support@poyalty.com <br />

</body>

</html>
