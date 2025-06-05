<?php
/*
Template Name: Process Email
*/
?>
<?php
    $toEmail = $_POST["userEmail"];
	$subject = 'Requested WHIT Home Product Info';
	
	$prodDetails= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html><body>';
	$prodDetails .= '<style type="text/css">
					table, tr, td {border:none; text-align:center; }
					</style>';
	$prodDetails .= '<table border="0" cellspacing="0" cellpadding="0" width="100%"><tr><td><div align="center"><table border="0" cellspacing="0" cellpadding="0" width="600" bgcolor="#c3d2d2"><tr><td>';
	$prodDetails .= '<tr bgcolor="#123962"><td align="left"><img width="160" src="https://web-dev2.sleepscore.com/wp-content/uploads/2019/01/SleepScore-New-Logo-White-01.png"/></td></tr>';
	
	$prodDetails .= '<tr><td border="0" align="center"><p><img src="https://web-dev2.sleepscore.com/wp-content/uploads/2019/02/sun-rise-icon.png" width="110" height="auto"/></p></td></tr>';
	$prodDetails .= '<tr><td border="0" align="center"><h2><strong>Thank you for visiting<br/>WHIT Home.</strong></h2></td></tr>';
	$prodDetails .= '<tr><td border="0" align=\"center\" style=\"padding-bottom:15px;\">Here is the product information you requested.</td></tr>';
	$prodDetails .=	'<tr><td align="center"><p style="font-size:20px;line-height:1.5; font-weight:300; margin-bottom:15px;">' . $_POST["prodTitle"] . '</p></td></tr>';
	
	$prodDetails .=	'<tr><td>
			<div align="center">
			    <table border="0" cellspacing="0" cellpadding="0" width="300" role="presentation">
                  <tr>
                    <td width="300">
						<img width="300" src="' . $_POST["prodImg"] . '"/>
					</td>
				  </tr>
				</table>
			  </div>
			  </td></tr>';
	$prodDetails .=	'<tr><td><div align="center">&nbsp;</div></td></tr>';
	
	$prodDetails .=	'<tr><td><table border="0" cellspacing="0" cellpadding="0" width="500"><tr><td><div align="center" style="padding:30px !important;"><br/><p>' . $_POST["prodDesc"] . '</p></div></td></tr></table></td></tr>';
	
	$prodDetails .=	'<tr><td><div bgcolor="#123962" align="center" width="400" style="display:block;padding:5px 30px;"><h2><a href="' . $_POST["prodURL"] . '"><p style="color:#ffffff;">Click here to view this product!</p><br /></a></h2></td></tr>';
	
	$prodDetails .= '<tr><td><img width="600" src="https://web-dev2.sleepscore.com/wp-content/uploads/2019/02/footer-bg.png" />';
	$prodDetails .= '</td></tr></table></div></td></tr></table>';
	$prodDetails .= '</body></html>';
	

	
	$headers = "From: support@sleepscore.com\r\n";
	$headers .= "Reply-To: support@sleepscore.com\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

    //$mailHeaders = "From: " . "<" . $_POST["userEmail"] .">\r\n";
	
    if(mail($toEmail, $subject , $prodDetails, $headers)) {
        print "<p class='success'>Mail Sent.</p>";
    } else {
        print "<p class='Error'>Problem in Sending Mail.</p>";
    }


?>