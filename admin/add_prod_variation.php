<?php 
include('internalaccess/session.php');
include('internalaccess/connectdb.php');
include_once("Include/activity_log.php");
include_once("sendmail_smtp.php");
include_once("../includes/functions.php");
include("resize-class.php");
$M_Menu_o4 = 'active';
$M_Menu_o4_dd = 'block';
$P_Menu_o1 = 'active';	
$product_data = array();
$prod_table = 'ab_product';
$variation_table = 'ab_product_variation';
$tbl_product_out_of_stock = 'product_out_of_stock';
$time_stamp = date("Y-m-d H:i:s");
$abpd_id = isset($_REQUEST['abpd_id']) ? $_REQUEST['abpd_id'] : 0 ;
function newimages($src,$compression,$alpha_channel,$file_format, $destdir,$newfilename,$width){
	include_once('../resizer/lib/class.resize.php');
	$ref=new imageResize;
	/*if((isset($_SERVER['HTTP_ACCEPT']) === true) && (strstr($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)){
		$new_2000 = $ref->resize($src,'mobile_images',2000,$compression,$file_format,$alpha_channel);
		$new_2000_name = $ref->newfilename;
	}else{
		$file_format=NULL;
		$new_2000_name = $src; // we don't need a new 2000 px file if the browser does't support the webp because alredy exist  
	}*/
	//echo "<br>S : ".$src."==> D :".$destdir."==> W :".$width."==> C :".$compression."==> FF :".$file_format."==> AC :".$alpha_channel."==> FN :".$newfilename;
	$new_300 = $ref->resize($src,$destdir,$width,$compression,$file_format,$alpha_channel,$destdir.$newfilename);
	$new_300_name = $ref->newfilename;
	/*$new_768 = $ref->resize($src,$destdir,248,$compression,$file_format,$alpha_channel,$newfilename);
	$new_768_name = $ref->newfilename;
	$new_1024 = $ref->resize($src,$destdir,1024,$compression,$file_format,$alpha_channel);
	$new_1024_name = $ref->newfilename;*/
	return array($new_300_name);
}
if(isset($_POST) && $_POST['type']=='update_notes_val'){
	$sel = "SELECT abpvn_id,abpvn_notes FROM ab_product_variation_notes WHERE abpvn_unit = '".$_POST['unit_name']."' ORDER BY CAST(abpvn_notes AS UNSIGNED), abpvn_notes";
	$sel_qry = mysqlQuery($sel);
	$html = '<option value="">Select Notes</option>';
	$found = 0;
	if(mysqlNumRow($sel_qry) > 0){
		while($sel_res = mysqlFetchArray($sel_qry)){
			$html .= '<option value="'.$sel_res['abpvn_notes'].'">'.$sel_res['abpvn_notes'].'</option>';
		}
		$found = 1;
	}
	echo json_encode(array('error'=>0,'html'=>$html,'found'=>$found)); exit;
}
if(isset($_POST) && $_POST['type']=='confirm_delete')
{
		$sc_id = mysqlRealescapestring($_POST['sc_id']);
		if($sc_id>0 && $_SESSION['admin_id']!=""){
			$delete_order_item_qry ="DELETE FROM ab_product_state_campaign WHERE id='".$sc_id."'";
			$delete_order_item_res = mysqlQuery($delete_order_item_qry);
			echo "confirm_deleted";
		}else{
			echo "not_deleted";
		}
		exit;
}
if($abpd_id > 0){
	// Update default variation when it is Inactive
	$sdvquery = "SELECT abpdv_id FROM ab_product_variation WHERE abpdv_is_default ='Y' AND abpdv_status!='Y' AND abpdv_abpd_id=".$abpd_id;
	$sdvresult = mysqlQuery($sdvquery);
	$is_record = mysqlAffectedRows($db);
	if($is_record > 0){
		$sdvrow = mysqlFetchArray($sdvresult);
		$abpdv_id = $sdvrow['abpdv_id'];
		$sdvuquery = "UPDATE ab_product_variation SET abpdv_is_default='N' WHERE abpdv_id=".$abpdv_id;
		$sdviresult = mysqlQuery($sdvuquery);
		//$sdvupdate = "UPDATE ab_product_variation SET abpdv_is_default='Y' WHERE abpdv_status='Y' AND abpdv_abpd_id=".$abpd_id." LIMIT 1";
		$sdvupdate = "UPDATE ab_product_variation AS apv
        JOIN (
            SELECT abpdv_id
            FROM ab_product_variation
            WHERE abpdv_status = 'Y'
              AND abpdv_abpd_id = ".$abpd_id."
            ORDER BY (abpdv_price - abpdv_discount) ASC
            LIMIT 1
        ) AS min_price ON apv.abpdv_id = min_price.abpdv_id
        SET apv.abpdv_is_default = 'Y'";
		$sdvres = mysqlQuery($sdvupdate);
		Activitylog('Product Default variant Updated 1 ',$abpd_id.'==>'.$sdvquery.'==> U1 :'.$sdvuquery.'==> U2: '.$sdvupdate, $_SESSION['admin_id'],'admin');
	}
	// end of update
	// Update default variation when it is out of stock
	$sdvquery = "SELECT abpdv_id FROM ab_product_variation WHERE abpdv_is_default ='Y' AND abpdv_qty<=0 AND abpdv_abpd_id=".$abpd_id;
	$sdvresult = mysqlQuery($sdvquery);
	$is_record = mysqlAffectedRows($db);
	if($is_record > 0){
		$sdvrow = mysqlFetchArray($sdvresult);
		$abpdv_id = $sdvrow['abpdv_id'];
		$sdvuquery = "UPDATE ab_product_variation SET abpdv_is_default='N' WHERE abpdv_id=".$abpdv_id;
		$sdviresult = mysqlQuery($sdvuquery);
		//$sdvupdate = "UPDATE ab_product_variation SET abpdv_is_default='Y' WHERE abpdv_status='Y' AND abpdv_qty>0 AND abpdv_abpd_id=".$abpd_id." LIMIT 1";
		$sdvupdate = "UPDATE ab_product_variation AS apv
         JOIN (
             SELECT abpdv_id
             FROM ab_product_variation
             WHERE abpdv_status = 'Y' 
               AND abpdv_qty > 0
               AND abpdv_abpd_id = ".$abpd_id."
             ORDER BY (abpdv_price - abpdv_discount) ASC
             LIMIT 1
         ) AS min_price ON apv.abpdv_id = min_price.abpdv_id
         SET apv.abpdv_is_default = 'Y'";
		$sdvres = mysqlQuery($sdvupdate);
		Activitylog('Product Default variant Updated 2 ',$abpd_id.'==>'.$sdvquery.'==> U1 :'.$sdvuquery.'==> U2: '.$sdvupdate, $_SESSION['admin_id'],'admin');
	}
	// end of update
	// Update variation to default when no any default set
	$svdquery = "SELECT abpdv_id FROM ab_product_variation WHERE abpdv_is_default ='Y' AND abpdv_abpd_id=".$abpd_id;
	$svdresult = mysqlQuery($svdquery) or die(mysqlError());
	$is_record = mysqlAffectedRows($db);
	if($is_record<=0){
		$sdvrow = mysqlFetchArray($svdresult);
		$abpdv_id = $sdvrow['abpdv_id'];
		//SET DEFAULT STATUS
		$sel = "SELECT abpdv_id FROM ab_product_variation WHERE abpdv_status='Y' AND abpdv_abpd_id=".$abpd_id." LIMIT 1";
		$sel_qry = mysqlQuery($sel);
		if(mysqlNumRow($sel_qry) > 0){
			//$sdvupdate = "UPDATE ab_product_variation SET abpdv_is_default='Y' WHERE abpdv_status='Y' AND abpdv_abpd_id=".$abpd_id." LIMIT 1";
			$sdvupdate = "UPDATE ab_product_variation AS apv
           JOIN (
               SELECT abpdv_id
               FROM ab_product_variation
               WHERE abpdv_status = 'Y'
                 AND abpdv_abpd_id = ".$abpd_id."
               ORDER BY (abpdv_price - abpdv_discount) ASC
               LIMIT 1
           ) AS min_price ON apv.abpdv_id = min_price.abpdv_id
           SET apv.abpdv_is_default = 'Y'";
			$sdvres = mysqlQuery($sdvupdate);
		}else{
			//$sdvupdate = "UPDATE ab_product_variation SET abpdv_is_default='Y' WHERE abpdv_abpd_id=".$abpd_id." LIMIT 1";
			$sdvupdate = "UPDATE ab_product_variation AS apv
             JOIN (
                 SELECT abpdv_id
                 FROM ab_product_variation
                 WHERE abpdv_abpd_id = ".$abpd_id."
                 ORDER BY (abpdv_price - abpdv_discount) ASC
                 LIMIT 1
             ) AS min_price ON apv.abpdv_id = min_price.abpdv_id
             SET apv.abpdv_is_default = 'Y'";
			$sdvres = mysqlQuery($sdvupdate);
		}
		Activitylog('Product Default variant Updated 3 ',$abpd_id.'==>'.$is_record.'==>'.$svdquery.'==> U3 :'.$sdvupdate, $_SESSION['admin_id'],'admin');
	}
	// end of update varation
}
/*clean meta title for url */
function clean($string) {
   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
   return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}
/* end --------------*/
if(isset($_POST) && $_POST['type']=='delete_guideline'){
	$abpd_id = $_POST['abpd_id'];
	$abpd_guildline_doc_qry = "SELECT abpd_guildline_doc FROM ab_product WHERE abpd_id = '".$abpd_id."'";
	$abpd_guildline_doc_res = mysqlQuery($abpd_guildline_doc_qry) or die($abpd_guildline_doc_qry);
	$abpd_guildline_doc_row = mysqlFetchArray($abpd_guildline_doc_res);
	$abpd_guildline_doc = $abpd_guildline_doc_row['abpd_guildline_doc']; 
	unlink('../images/guildline_document/'.$abpd_guildline_doc);
	$del_guildline_doc_query = "UPDATE ab_product SET abpd_guildline_doc = '' WHERE abpd_id = '".$abpd_id."'";
	$del_guildline_doc_res = mysqlQuery($del_guildline_doc_query) or die($del_guildline_doc_query);
	$is_delete = mysqlAffectedRows($db);
	if($is_delete > 0){
		echo "success";
	}
	exit();
}
if(isset($_POST) && $_POST['type']=='delete_facebook_feed_img'){
	$abpd_id = $_POST['abpd_id'];
	$abpd_facebook_feed_img_qry = "SELECT abpd_facebook_feed_img FROM ab_product WHERE abpd_id = '".$abpd_id."'";
	$abpd_facebook_feed_img_res = mysqlQuery($abpd_facebook_feed_img_qry) or die($abpd_facebook_feed_img_qry);
	$abpd_facebook_feed_img_row = mysqlFetchArray($abpd_facebook_feed_img_res);
	$abpd_facebook_feed_img = $abpd_facebook_feed_img_row['abpd_facebook_feed_img']; 
	unlink('images/prod_image/'.$abpd_facebook_feed_img);
	$del_facebook_feed_img_query = "UPDATE ab_product SET abpd_facebook_feed_img = '' WHERE abpd_id = '".$abpd_id."'";
	$del_facebook_feed_img_res = mysqlQuery($del_facebook_feed_img_query) or die($del_facebook_feed_img_query);
	$is_delete = mysqlAffectedRows($db);
	if($is_delete > 0){
		echo "success";
	}
	exit();
}
if(isset($_POST) && $_POST['type']=='delete_aplus_img')
{
	$abpd_id = $_POST['abpd_id'];
	$abpd_aplus_img_qry = "SELECT abpd_aplus_img FROM ab_product WHERE abpd_id = '".$abpd_id."'";
	$abpd_aplus_img_res = mysqlQuery($abpd_aplus_img_qry) or die($abpd_aplus_img_qry);
	$abpd_aplus_img_row = mysqlFetchArray($abpd_aplus_img_res);
	$abpd_aplus_img = $abpd_aplus_img_row['abpd_aplus_img']; 
	unlink('images/prod_image/'.$abpd_aplus_img);
	$del_aplus_img_query = "UPDATE ab_product SET abpd_aplus_img = '' WHERE abpd_id = '".$abpd_id."'";
	$del_aplus_img_res = mysqlQuery($del_aplus_img_query) or die($del_aplus_img_query);
	$is_delete = mysqlAffectedRows($db);
	// if($is_delete > 0){
    if($del_aplus_img_res){
		echo "success";
	}
	exit();
}
if(isset($_POST) && $_POST['action']=='delete_variation_unit'){
	$sel = mysqlQuery("SELECT abpdu_id,abpdu_image_id FROM ab_product_variation_unit WHERE abpdu_id = '".$_POST['row_id']."' ");
	$sel_res = mysqlFetchArray($sel);
	if($sel_res['abpdu_image_id']!=0){
		$sel_img = mysqlQuery("SELECT abpi_image FROM ab_product_image WHERE abpi_id = '".$sel_res['abpdu_image_id']."' ");
		if(mysqlNumRow($sel_img) > 0){
			$sel_img_res = mysqlFetchArray($sel_img);
			if($sel_img_res['abpi_image']!=''){
				unlink('images/'.$sel_img_res['abpi_image']);
			}
			$del = mysqlQuery("DELETE FROM ab_product_image WHERE abpi_id = '".$sel_res['abpdu_image_id']."' ");
		}
	}
	$del = mysqlQuery("DELETE FROM ab_product_variation_unit WHERE abpdu_id = '".$_POST['row_id']."' ");
}
/* SEND PRODUCT AVAILABILITY NOTIFICATION TO USER */
if(isset($_POST) && $_POST['submit'] != '' && ($_POST['old_abpd_in_stock'] == 'N' && $_POST['abpd_in_stock'] == 'Y' && $_POST['abpd_active'] == 'Y') || ($_POST['abpd_in_stock'] == 'Y' && $_POST['abpd_active'] == 'Y')){
	$abpd_id = $_REQUEST['abpd_id'];
	$userstonotifiy = "SELECT id, ab_notify_user_contact FROM ab_notify_user LEFT JOIN ab_product ON abpd_id = ab_notify_product_id WHERE ab_notify_product_id = '".$abpd_id."' AND sent_status = 'N'";
	$userstonotifiyRes = mysqlQuery($userstonotifiy);
	$prodName = $_POST['abpd_name'];
	$metaTitle = $_POST['abpd_meta_title'];
	$prod_foler = "products/";
	$prod_meta_url = str_replace(" ", "_",strtolower(clean($metaTitle))); 
	$product_url = $SITE_TITLE_URL.$prod_foler.$prod_meta_url;
	if(mysqlNumRow($userstonotifiyRes) ){
		while($user = mysqlFetchArray($userstonotifiyRes)){
			/* UPDATE NOTIFY STATUS */
			$update_notify_status = mysqlQuery('UPDATE ab_notify_user SET sent_status = "Y" WHERE id = "'.$user['id'].'"');
			//$message = "Thank you for interesting in product ".$prodName." is now available on www.agribegri.com! You can order it on".$product_url;
			$message = "We would glad to inform you that ".$prodName." is now available on cheap Agro Store www.agribegri.com. You can see it on ".$product_url.".php";
			$message = urlencode($message);
			$mobile = $user['ab_notify_user_contact'];
			if(IS_BETA_ENVIRONEMENT=='N'){
				$ch = curl_init("http://smshorizon.co.in/api/sendsms.php?user=".$USER_NAME_API."&apikey=".$API_KEY."&mobile=".$mobile."&message=".$message."&senderid=".$SENDER_ID."&type=".$TYPE_KEY."&tid=1207161536746225353"); 
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
				$output = curl_exec($ch);      
				curl_close($ch);
			}
		}
	}
}
if(isset($_POST['submit']) && $_POST['submit']!=''){
//	echo "<pre>"; print_r($_POST); print_r($_FILES); exit;
	$abpd_id = $_POST['abpd_id'];
	$abpd_cat_id = $_POST['abpd_cat_id'];
	$ab_subcat_id = intval($_POST['ab_subcat_id']);
	$ab_childcat_id = intval($_POST['ab_childcat_id']);
	$abpd_comp_id = $_POST['abpd_comp_id'];
	$abpd_absupplier_id = $_POST['abpd_absupplier_id'];
	$abpd_absupplier_id = ($abpd_absupplier_id=="")? '1' : $abpd_absupplier_id;
	$abpd_user_id = $_POST['abpd_user_id'];
	$abpd_name = $_POST['abpd_name'];
	$abpd_technical_id = $_POST['abpd_technical_id'];
	$abpd_same_product_id = $_POST['abpd_same_product_id'];
	/*$abpd_technical_name = $_POST['abpd_technical_name'];*/
	$abpd_descri = $_POST['abpd_descri'];
	$abpd_descri = utf8_encode($abpd_descri);
	if($abpd_absupplier_id == 96 || $abpd_absupplier_id == 1219 || $abpd_absupplier_id == 2972){
		$abpd_farmers_choice = 'Y';
	}else{
		$abpd_farmers_choice = 'N';
	}
	//echo $abpd_farmers_choice; exit;
	 
	if($_POST['submit']=='save_draft'){
		$abpd_active = 'N';
	}else{
		$abpd_active = $_POST['abpd_active'];
	}	
	$abpd_original_status = $_POST['abpd_original_status'];
	$abpd_product_tags = $_POST['abpd_product_tags'];
	$abpd_gst_no = $_POST['abpd_gst_no'];
	$abpd_hsn_code = $_POST['abpd_hsn_code'];
	$coda = '';
	$abpd_returnable = (isset($_POST['abpd_returnable'])) ? 'Y' : 'N';
	$abpd_shipping_through = '';
	$abpd_meta_title = $_POST['abpd_meta_title'];
	$abpd_meta_keyword = ''; //$_POST['abpd_meta_keyword'];
	$abpd_meta_desc = $_POST['abpd_meta_desc'];
	$rate_compare = $_POST['rate_compare'];
	$rand_idads = $_POST['rand_idads'];
	$abpd_video = $_POST['abpd_video'];
	$abpd_google_enable = $_POST['abpd_google_enable']; //(isset($_POST['abpd_google_enable'])) ? 'Y' : 'N';
	$abpd_facebook_enable = (isset($_POST['abpd_facebook_enable'])) ? 'Y' : 'N';
	$abpd_agribegri_fulfilment 		= (isset($_POST['abpd_agribegri_fulfilment'])) ? 'Y' : 'N';
	$abpd_product_type = mysqlRealescapestring($_POST['abpd_product_type']);
	$abpd_google_product_category = mysqlRealescapestring($_POST['abpd_google_product_category']);
	$abpd_bharat_agri_margin_per = $_POST['abpd_bharat_agri_margin_per'];
	$chk_approve11 = $_POST['chk_approve11'];
	$chk_approve20 = $_POST['chk_approve20'];
	$abpd_google_crawl = (isset($_POST['abpd_google_crawl'])) ? 'Y' : 'N';
	$abpd_farmers_only = (isset($_POST['abpd_farmers_only'])) ? 'Y' : 'N';
	// echo '<pre>'; die(print_r($_POST));
	$clean_meta_title = clean($abpd_meta_title);
	$abpd_cropcat_id = 0;
	if(!empty($_POST['ab_cropcat_id'])){
		$abpd_cropcat_id=implode(',',$_POST['ab_cropcat_id']);
	}
	$abpd_season_month = 0;
	if(!empty($_POST['abpd_season_month'])){
		$abpd_season_month=implode(',',$_POST['abpd_season_month']);
	}
	//$abpd_cropcat_id=intval($_POST['ab_cropcat_id']);
	$abpd_country_id=($_POST['ab_country_id']=="")? 0 : $_POST['ab_country_id']; 
	$abpd_having_color='N';
	$abpd_product_color='';
	if(isset($_POST['abpd_having_color']))
	{
		$abpd_having_color = $_POST['abpd_having_color'];
		$abpd_product_color = $_POST['abpd_product_color'];
	}
	$abpd_out_of_stock='N';
	if(isset($_POST['abpd_out_of_stock'])){
		$abpd_out_of_stock = $_POST['abpd_out_of_stock'];
	}
	$abpd_multi_seller_product='N';
	if(isset($_POST['abpd_multi_seller_product'])){
		$abpd_multi_seller_product = $_POST['abpd_multi_seller_product'];
	}
	$abpd_display_transport_variant = 'N';
	if(isset($_POST['abpd_display_transport_variant'])){
		$abpd_display_transport_variant = $_POST['abpd_display_transport_variant'];
	}
	$abpd_allow_advance_payment = $_POST['abpd_allow_advance_payment'];
	//echo "<br>".$query = "SELECT abpd_id FROM ab_product WHERE abpd_meta_title ='".$clean_meta_title."' AND abpd_id!='".$abpd_id."'"; 
	//exit;	
	$send_seller_mail = false;
	if(is_prod_meta_title_exists($clean_meta_title, $abpd_id) ){
		//	$err_msg = "Meta title already exists for other product.";
		$yourArray = explode("-",$clean_meta_title);
		$myLastElement = end($yourArray);
		if(is_numeric($myLastElement)){
			$clean_meta_title = substr($clean_meta_title,0,-strlen($myLastElement));
			$myLastElement = ($myLastElement) + 1;
			$clean_meta_title = $clean_meta_title.$myLastElement;
		}else{
			$clean_meta_title = $clean_meta_title.'-1';
		}
		//echo "M:".$clean_meta_title; exit;
	}
	{
		$err_msg = '';
		generate_prod_change_log($abpd_id, $_SESSION['admin_id'], 'AdminPanel');
		if($abpd_absupplier_id!='' && $abpd_absupplier_id!='1' && $abpd_active!='N'){
			if(is_prod_already_approved($abpd_id) ){
				$getDataBy = array(
					'absl_id' => $abpd_absupplier_id,
				);
				$sellerData = $pdo->get_column_data('ab_seller', 'absl_email, absl_name', $getDataBy);
				$sellerData = $sellerData[0];
				$seller_name =  $sellerData['absl_name'];	
				$seller_email = $sellerData['absl_email'];	
				$send_seller_mail  = true;					
				$updataData = array(
					'abpd_approved_date' => date('Y-m-d')
				);
				$updateBy = array(
					'abpd_id' => $abpd_id
				);
				$pdo->beginTransaction();
				$pdo->_update($prod_table, $updataData, $updateBy);
				$pdo->endTransaction();
			}
		}
		/*$check_slug_generated_row = $pdo->get_column_data($prod_table, 'abpd_meta_title', $getDataBy);		
		$check_slug_generated_row = $check_slug_generated_row[0];
		if($check_slug_generated_row['abpd_meta_title'] == ''){
			$clean_meta_title = clean($abpd_meta_title);
		}else{
			$clean_meta_title = $check_slug_generated_row['abpd_meta_title'];
		}*/
		/*$check_slug_generated_qry = "SELECT abpd_meta_title FROM ab_product WHERE abpd_id = '".$abpd_id."'";
		$check_slug_generated_res = mysql_query($check_slug_generated_qry);
		$check_slug_generated_row = mysql_fetch_array($check_slug_generated_res);
		if($check_slug_generated_row['abpd_meta_title'] == ''){
			$clean_meta_title = clean($abpd_meta_title);
		}else{
			$clean_meta_title = $check_slug_generated_row['abpd_meta_title'];
		}*/
		$strupdatemeta = array();
		$check_slug_generated_qry = "SELECT abpd_meta_title FROM ab_product WHERE abpd_id = '".$abpd_id."'";
		$check_slug_generated_res = mysqlQuery($check_slug_generated_qry);
		$check_slug_generated_row = mysqlFetchArray($check_slug_generated_res);
		if($check_slug_generated_row['abpd_meta_title'] == ''){
			//$clean_meta_title = clean($abpd_meta_title);
			//$strupdatemeta = "'abpd_meta_title' => $clean_meta_title,";
			$strupdatemeta = array(
				'abpd_meta_title' => $clean_meta_title
			);
		}
		if($_REQUEST['debug']=="1"){
			echo $check_slug_generated_qry;
			echo "<br>EM:".$check_slug_generated_row['abpd_meta_title'];
			echo "<br>CM:".clean($abpd_meta_title);
			//exit;
		}
		if($abpd_id==''){
			Activitylog('Product Added Meta Title',$clean_meta_title, $_SESSION['admin_id'],'admin');
		}
		$prodInsUpdData = array(
			'abpd_cat_id' => $abpd_cat_id,
			'abpd_subcat_id' => $ab_subcat_id,
			'abpd_childcat_id' => $ab_childcat_id,
			'abpd_comp_id' => $abpd_comp_id,
			'abpd_absupplier_id' => $abpd_absupplier_id,
			'abpd_user_id' => $abpd_user_id,
			'abpd_name' => mysqlRealescapestring($abpd_name),
			'rate_compare' => $rate_compare,
			'abpd_descri' => mysqlRealescapestring($abpd_descri),
			'abpd_active' => $abpd_active,
			'abpd_gst_no' => $abpd_gst_no,
			'abpd_hsn_code'=>$abpd_hsn_code,
			'coda' => $coda,
			'abpd_product_tags' => mysqlRealescapestring($abpd_product_tags),
			'abpd_meta_title' => mysqlRealescapestring($clean_meta_title),
			'abpd_new_meta_title' => mysqlRealescapestring($abpd_meta_title),
			'abpd_meta_keyword' => mysqlRealescapestring($abpd_meta_keyword),
			'abpd_meta_desc' => mysqlRealescapestring($abpd_meta_desc),
			'abpd_returnable' => $abpd_returnable,
			'abpd_shipping_through'	=> $abpd_shipping_through,
			'abpd_video' => $abpd_video,
			'abpd_product_type' => $abpd_product_type,
			'abpd_google_product_category' => $abpd_google_product_category,
			'abpd_bharat_agri_margin_per' => $abpd_bharat_agri_margin_per,
			'abpd_google_enable' => $abpd_google_enable,
			'abpd_facebook_enable' => $abpd_facebook_enable,
			'abpd_agribegri_fulfilment' => $abpd_agribegri_fulfilment,
			'abpd_technical_id' => $abpd_technical_id,
			'abpd_same_product_id' => $abpd_same_product_id,
			'abpd_having_color' => $abpd_having_color,
			'abpd_display_transport_variant' => $abpd_display_transport_variant,
			'abpd_product_color' => $abpd_product_color,
			'abpd_country_id' => $abpd_country_id,
			'abpd_cropcat_id' => $abpd_cropcat_id,
			'abpd_season_month'	=> $abpd_season_month,
			'abpd_allow_advance_payment'=>$abpd_allow_advance_payment,
			'abpd_multi_seller_product'=>$abpd_multi_seller_product,
			'abpd_google_crawl'=>$abpd_google_crawl,
			'abpd_farmers_only'=>$abpd_farmers_only,
			'created_from'=>'admin',
			'created_by'=>$_SESSION['admin_id'],
			'abpd_farmers_choice'=>$abpd_farmers_choice
		);
		if($abpd_id>0){
			$prodInsUpdData = array(
				'abpd_cat_id' => $abpd_cat_id,
				'abpd_subcat_id' => $ab_subcat_id,
				'abpd_childcat_id' => $ab_childcat_id,
				'abpd_comp_id' => $abpd_comp_id,
				'abpd_absupplier_id' => $abpd_absupplier_id,
				'abpd_user_id' => $abpd_user_id,
				'abpd_name' => mysqlRealescapestring($abpd_name),
				'rate_compare' => $rate_compare,
				'abpd_descri' => mysqlRealescapestring($abpd_descri),
				'abpd_active' => $abpd_active,
				'abpd_gst_no' => $abpd_gst_no,
				'abpd_hsn_code'=> $abpd_hsn_code,
				'coda' => $coda,
				'abpd_product_tags' => mysqlRealescapestring($abpd_product_tags),
				'abpd_new_meta_title' => mysqlRealescapestring($abpd_meta_title),
				'abpd_meta_keyword' => mysqlRealescapestring($abpd_meta_keyword),
				'abpd_meta_desc' => mysqlRealescapestring($abpd_meta_desc),
				'abpd_returnable' => $abpd_returnable,
				'abpd_shipping_through'	=> $abpd_shipping_through,
				'abpd_video' => $abpd_video,
				'abpd_product_type' => $abpd_product_type,
				'abpd_google_product_category' => $abpd_google_product_category,
				'abpd_bharat_agri_margin_per' => $abpd_bharat_agri_margin_per,
				'abpd_google_enable' => $abpd_google_enable,
				'abpd_facebook_enable' => $abpd_facebook_enable,
				'abpd_agribegri_fulfilment' => $abpd_agribegri_fulfilment,
				'abpd_technical_id' => $abpd_technical_id,
				'abpd_same_product_id' => $abpd_same_product_id,
				'abpd_having_color' => $abpd_having_color,
				'abpd_display_transport_variant' => $abpd_display_transport_variant,
				'abpd_product_color' => $abpd_product_color,
				'abpd_country_id' => $abpd_country_id,
				'abpd_cropcat_id' => $abpd_cropcat_id,
				'abpd_season_month'	=> $abpd_season_month,
				'abpd_allow_advance_payment'=>$abpd_allow_advance_payment,
				'abpd_multi_seller_product'=>$abpd_multi_seller_product,
				'abpd_google_crawl'=>$abpd_google_crawl,
				'abpd_farmers_only'=>$abpd_farmers_only,
				'abpd_farmers_choice'=>$abpd_farmers_choice
			);
			if(count($strupdatemeta)>0){
				$prodInsUpdData = array_merge($prodInsUpdData,$strupdatemeta);
			}
		}
		/*****abpd_guildline_doc start*****/
		if(isset($_FILES['abpd_guildline_doc'])){
			$abpd_guildline_doc_name = $_FILES['abpd_guildline_doc']['name'];
			//$file_size = $_FILES['abpd_guildline_doc']['size'];
			/*if($file_size>1048576){
				$err_msg = 'Please upload less than 1MB file for guideline document!'; 
			}*/
			$doc_ext = pathinfo($abpd_guildline_doc_name, PATHINFO_EXTENSION); 
			$abpd_guildline_doc_name = basename($abpd_guildline_doc_name,".".$doc_ext);
			$abpd_guildline_doc_name = $abpd_guildline_doc_name.'_'.date('Y_m_d_h_i_s');
			$abpd_guildline_doc_name = str_replace(" ", "_", $abpd_guildline_doc_name).'.'.$doc_ext;
			$tmp_name = $_FILES['abpd_guildline_doc']['tmp_name'];
			if($_FILES['abpd_guildline_doc']['tmp_name'] != ''){
				move_uploaded_file($tmp_name, "../images/guildline_document/" . $abpd_guildline_doc_name);
				$prodInsUpdData['abpd_guildline_doc'] = $abpd_guildline_doc_name;
			}
		}
		/*****abpd_guildline_doc end*****/
		/*****abpd_product_default_image start*****/
		if(isset($_FILES['abpd_default_img'])){
			//echo $_POST['abpd_old_default_img']; exit;
			$extension=array('png','jpg','jpeg','webp');
			$abpd_default_img_name = $_FILES['abpd_default_img']['name'];
			$default_image_ext = pathinfo($abpd_default_img_name, PATHINFO_EXTENSION); 
			if(in_array(strtolower($default_image_ext),$extension)){
				$filename=basename($abpd_default_img_name,$default_image_ext);
				$newFileName=rand().time().".".$default_image_ext;
            move_uploaded_file($_FILES['abpd_default_img']['tmp_name'],"images/prod_image/".$newFileName);	
            $prodInsUpdData['abpd_image'] = 'prod_image/'.$newFileName;
            if(strtolower($default_image_ext)!='webp'){
	            $resizeObj = new resize('images/prod_image/'.$newFileName);
					// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
					$resizeObj -> resizeImage(222, 255, 'auto');
					// *** 3) Save image ('image-name', 'quality [int]')
					$resizeObj -> saveImage('images/prod_image/thumb/thumb222255_'.$newFileName, 100);
					// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
					$resizeObj -> resizeImage(100, 125, 'auto');
					// *** 3) Save image ('image-name', 'quality [int]')
					$resizeObj -> saveImage('images/prod_image/thumb/thumb_'.$newFileName, 100);
					// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
					$resizeObj -> resizeImage(248, 242, 'auto');
					// *** 3) Save image ('image-name', 'quality [int]')
					$resizeObj -> saveImage('images/prod_image/thumb/thumb248242_'.$newFileName, 100);
				}if(strtolower($default_image_ext)=='webp'){
					$mainimage = 'images/prod_image/'.$newFileName;
					$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb222255_'.$newFileName, 255);
					$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb248242_'.$newFileName,242);
					$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb_'.$newFileName,125);
					/*$cpy_name1 = "images/prod_image/".$newFileName;
					$cpy_name2 = "images/prod_image/".$newFileName;
					$cpy_name3 = "images/prod_image/".$newFileName;
					$thumb1_nm = 'thumb222255_'.$newFileName;
					$thumb2_nm = 'thumb_'.$newFileName;
					$thumb3_nm = 'thumb248242_'.$newFileName;
					copy($cpy_name1,'images/prod_image/thumb/'.$thumb1_nm);
					copy($cpy_name2,'images/prod_image/thumb/'.$thumb2_nm);
					copy($cpy_name3,'images/prod_image/thumb/'.$thumb3_nm);*/
					//move_uploaded_file($_FILES['abpd_default_img']['tmp_name'],"images/prod_image/thumb/".$thumb2_nm);
					//move_uploaded_file($_FILES['abpd_default_img']['tmp_name'],"images/prod_image/thumb/".$thumb3_nm);
				}
				if($_POST['abpd_old_default_img']!=''){
					$del_default_img = "DELETE FROM ab_product_image WHERE abpi_image = '".$_POST['abpd_old_default_img']."' AND abpi_product_id = '".$abpd_id."' ";
					$del_default_img_qry = mysqlQuery($del_default_img);
					unlink('images/'.$_POST['abpd_old_default_img']);
				}
				$sel_prod_img = mysqlFetchArray(mysqlQuery("SELECT abpi_order_no FROM ab_product_image WHERE abpi_product_id = '".$abpd_id."' ORDER BY abpi_id DESC LIMIT 1"));
				$img_order_no = $sel_prod_img['abpi_order_no'] + 1;
				$default_image_nm = 'prod_image/'.$newFileName;
				$ins_img = "INSERT INTO ab_product_image SET abpi_product_id = '".$abpd_id."', abpi_image = '".$default_image_nm."', abpi_order_no = '0' ";
				mysqlQuery($ins_img);
			}
		}
		/*****abpd_product_default_image end*****/
		/*****abpd_facebook_feed_img start*****/
		if(isset($_FILES['abpd_facebook_feed_img'])){
			$abpd_facebook_feed_img_name = $_FILES['abpd_facebook_feed_img']['name'];
			//$file_size = $_FILES['abpd_guildline_doc']['size'];
			/*if($file_size>1048576){
				$err_msg = 'Please upload less than 1MB file for guideline document!'; 
			}*/
			$doc_img_ext = pathinfo($abpd_facebook_feed_img_name, PATHINFO_EXTENSION);
			$abpd_facebook_feed_img_name=basename($abpd_facebook_feed_img_name,$doc_img_ext);
         $abpd_facebook_feed_img_name=rand().time().".".$doc_img_ext;
			$tmp_name = $_FILES['abpd_facebook_feed_img']['tmp_name'];
			if($_FILES['abpd_facebook_feed_img']['tmp_name'] != ''){
				move_uploaded_file($tmp_name, "images/prod_image/" . $abpd_facebook_feed_img_name);
				$prodInsUpdData['abpd_facebook_feed_img'] = $abpd_facebook_feed_img_name;
				if($_POST['hidden_abpd_facebook_feed_img']!=''){
					unlink('images/prod_image/'.$_POST['hidden_abpd_facebook_feed_img']);
				}
			}
		}
		/*****abpd_facebook_feed_img end*****/
    	/*****abpd_aplus_img start*****/
		if(isset($_FILES['abpd_aplus_img'])){
			$abpd_aplus_img_name = $_FILES['abpd_aplus_img']['name'];
			$aplus_img_ext = pathinfo($abpd_aplus_img_name, PATHINFO_EXTENSION);
			$abpd_aplus_img_name=basename($abpd_aplus_img_name,$aplus_img_ext);
         	$abpd_aplus_img_name=rand().time().".".$aplus_img_ext;
			$aplus_tmp_name = $_FILES['abpd_aplus_img']['tmp_name'];
			if($_FILES['abpd_aplus_img']['tmp_name'] != ''){
				move_uploaded_file($aplus_tmp_name, "images/prod_image/" . $abpd_aplus_img_name);
				$prodInsUpdData['abpd_aplus_img'] = $abpd_aplus_img_name;
				if($_POST['hidden_abpd_aplus_img']!=''){
					unlink('images/prod_image/'.$_POST['hidden_abpd_aplus_img']);
				}
			}
		}
		/*****abpd_aplus_img end*****/
		/* check meta title generated or not */	
		$getDataBy = array(
			'abpd_id' => $abpd_id
		);
		$fieldsStr = $valuesStr = $updateStr = $whereStr = '';
		$updateBy = array(
			'abpd_id' => $abpd_id
		);
		if($abpd_id>0){
			$prodInsUpdData['abpd_name_variation'] = mysqlRealescapestring($abpd_name);
			foreach( $prodInsUpdData as $tmpK => $tmpV ) {
				$updateStr .= " `".$tmpK."` = '".$tmpV."', ";
			}
			$updateStr = trim($updateStr, ', ');
			if(is_numeric($abpd_id)){
				$strchk11 = "";
				if($chk_approve11!=""){
					$strchk11 = ", abpd_approve11='Y', abpd_approve11_datetime = NOW()";	
				}
				$strchk20 = "";
				if($chk_approve20!=""){
					$strchk20 = ", abpd_approve20='Y', abpd_approve20_datetime = NOW()";	
				}
				$qry = "UPDATE `".$prod_table."` SET ".$updateStr.$strchk11.$strchk20." WHERE `abpd_id` = ".$abpd_id;
				if($_REQUEST['debug']==1){
					echo $qry; exit;
				}
				$res = mysqlQuery($qry) or die(mysqlError());
				// $pdo->beginTransaction();
				// $res = $pdo->_update($prod_table, $prodInsUpdData, $updateBy);
				// $pdo->endTransaction();
				$msg = 'up';
				//Activitylog('Product Updated',$abpd_name.' Successfully Updated', $_SESSION['admin_id'],'admin', serialize($product_data) );
				Activitylog('Product Updated',$abpd_id.' Successfully Updated', $_SESSION['admin_id'],'admin');
				$variationDeleteBy = array(
					'abpdv_abpd_id' => $abpd_id
				);
				$prod_code = $_POST['abpd_code'];
				//$pdo->_delete($variation_table, $variationDeleteBy);
				if($abpd_original_status!=$abpd_active && $abpd_active=="Y"){
					$ch = curl_init($SITE_TITLE_URL."prodimage-resize.php?abpd_id=".$abpd_id); 
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
					$output = curl_exec($ch);      
					curl_close($ch);
				}
			}
		}else{
			$prod_code = $prodInsUpdData['abpd_code'] = generate_prodcuct_code();
			if( isset($prodInsUpdData['abpd_bharat_agri_margin_per']) && $prodInsUpdData['abpd_bharat_agri_margin_per'] == '' ) {
				$prodInsUpdData['abpd_bharat_agri_margin_per'] = (float)(0);
			}
			foreach( $prodInsUpdData as $tmpK => $tmpV ) {
				$fieldsStr .= " `".$tmpK."`, ";
				$valuesStr .= " '".trim($tmpV)."', ";
			}
			$fieldsStr = trim($fieldsStr, ', ');
			$valuesStr = trim($valuesStr, ', ');
			$qry = "INSERT INTO `".$prod_table."` (".$fieldsStr.") VALUES (".$valuesStr.")";
			$res = mysqlQuery($qry);
			$last_ins_id = mysqlLastId(); 
			// $pdo->beginTransaction();
			// $last_ins_id = $pdo->_insert($prod_table, $prodInsUpdData);
			// $pdo->endTransaction();
			if(isset($_FILES['abpd_default_img'])){
				$default_image_nm = 'prod_image/'.$newFileName;
				$ins_img = "INSERT INTO ab_product_image SET abpi_product_id = '".$last_ins_id."', abpi_image = '".$default_image_nm."', abpi_order_no = '0' ";
				mysqlQuery($ins_img);
			}
			$msg = 'in';
			Activitylog('Product Added',$last_ins_id.' Successfully Added - '.$abpd_min_order_qty,$_SESSION['admin_id'],'admin');
		}
	}
	if($err_msg==""){
		/*** add/update recomandation procucts ***/
		if($abpd_id > 0){
			$res_ins_id = $abpd_id;	
		}else{
			$res_ins_id = $last_ins_id;	
		}
		// recommanded product delete and add start
		$deleteBy = array(
			'abpr_product_id' => $res_ins_id
		);
		$pdo->beginTransaction();
		$pdo->_delete("ab_product_recomanded_products", $deleteBy);
		$pdo->endTransaction();
		if(!empty($_POST['abpd_recomanded_prod'])){
			$pdo->beginTransaction();
			foreach($_POST['abpd_recomanded_prod'] as $recomand_prod ){
				$recmdProdData = array(
					"abpr_product_id" => $res_ins_id,
					"abpr_recomded_product_id" => $recomand_prod
				);
				$imageId = $pdo->_insert('ab_product_recomanded_products', $recmdProdData);
			}
			$pdo->endTransaction();
		}
		// recommanded product delete and add end
		/*** add/update product images ***/
		$ran_id = ( $abpd_id > 0 ) ? $abpd_id : $rand_idads;
		$getImagesBy = array('abpi_product_id' => $ran_id);
		$tempimagesQry = 'SELECT abpi_id, abpi_product_id, abpi_image FROM ab_product_image_temp WHERE abpi_product_id = :abpi_product_id';
		$tempimagesQry = $pdo->_prepare($tempimagesQry);
		$pdo->_bindParams($getImagesBy);
		$pdo->_execute();
		$tempimages = $pdo->_resultset();
		if(count($tempimages) > 0){
			$img_counter = 1;
			$pdo->beginTransaction();
			foreach ($tempimages as $key => $img_row) {
				$sel_prod_img = mysqlFetchArray(mysqlQuery("SELECT abpi_order_no FROM ab_product_image WHERE abpi_product_id = '".$res_ins_id."' ORDER BY abpi_order_no DESC LIMIT 1"));
				$img_order_no = $sel_prod_img['abpi_order_no'] + 1;
				$imagename = $img_row['abpi_image'];
				$temp_image_ext = pathinfo($imagename, PATHINFO_EXTENSION);
				$name = explode("/",$imagename);
				if(strtolower($temp_image_ext)!='webp'){
					$resizeObj = new resize('images/'.$imagename);
					// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
					$resizeObj -> resizeImage(222, 255, 'auto');
					// *** 3) Save image ('image-name', 'quality [int]')
					$resizeObj -> saveImage('images/'.$name[0].'/thumb/thumb222255_'.$name[1], 100);
					// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
					$resizeObj -> resizeImage(100, 125, 'auto');
					// *** 3) Save image ('image-name', 'quality [int]')
					$resizeObj -> saveImage('images/'.$name[0].'/thumb/thumb_'.$name[1], 100);
					// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
					$resizeObj -> resizeImage(248, 242, 'auto');
					// *** 3) Save image ('image-name', 'quality [int]')
					$resizeObj -> saveImage('images/'.$name[0].'/thumb/thumb248242_'.$name[1], 100);
				}if(strtolower($temp_image_ext)=='webp'){
					$mainimage = 'images/prod_image/'.$imagename;
					$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb222255_'.$imagename, 255);
					$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb248242_'.$imagename,242);
					$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb_'.$imagename,125);
					/*$cpy_name1 = "images/".$imagename;
					$cpy_name2 = "images/".$imagename;
					$cpy_name3 = "images/".$imagename;
					copy($cpy_name1,'images/'.$name[0].'/thumb/thumb222255_'.$name[1]);
					copy($cpy_name2,'images/'.$name[0].'/thumb/thumb_'.$name[1]);
					copy($cpy_name3,'images/'.$name[0].'/thumb/thumb248242_'.$name[1]);*/
				}
				$imageData = array(
					'abpi_product_id' => $res_ins_id,
					'abpi_image' => $img_row['abpi_image']
				);
				$ins_img = "INSERT INTO ab_product_image SET abpi_product_id = '".$res_ins_id."', abpi_image = '".$img_row['abpi_image']."', abpi_order_no = '".$img_order_no."' ";
				mysqlQuery($ins_img);
				$imageId = mysqlLastId();
				//$imageId = $pdo->_insert('ab_product_image', $imageData);
				/**** set first image as primary and update it to product table ****/
				if($img_counter >= 1){
					/*$primaryImgData = array( 'abpd_image' => $img_row['abpi_image'] );
					$updateimgBy = array( 'abpd_id' => $res_ins_id );
					$pdo->_update($prod_table, $primaryImgData, $updateimgBy);*/
				}
				$img_counter++;
			}
			$pdo->endTransaction();
			/*** delete all images from temp table ****/
			$pdo->beginTransaction();
			$pdo->_delete('ab_product_image_temp', $getImagesBy);
			$pdo->endTransaction();
		}
		$select_parimary = "SELECT abpi_image FROM ab_product_image WHERE abpi_product_id = '".$abpd_id."' ORDER BY abpi_order_no ASC LIMIT 1";
		$pdo->_prepare($select_parimary); 
		$res_p = $pdo->_resultset();
		/*$primaryImgData = array('abpd_image' => $res_p[0]['abpi_image']);
		$updateimgBy = array('abpd_id' => $abpd_id );
		$pdo->beginTransaction();
		$pdo->_update($prod_table, $primaryImgData, $updateimgBy);
		$pdo->endTransaction();*/
		$select_parimary_img = "SELECT abpi_image FROM ab_product_image WHERE abpi_product_id = '".$abpd_id."' ORDER BY abpi_order_no ASC";
		$pdo->_prepare($select_parimary_img); 
		$res_p_img = $pdo->_resultset();
		if(count($res_p_img[0]) == 0){
			/*$set_primaryImgData = array('abpd_image' => '');
			$set_updateimgBy = array('abpd_id' => $abpd_id );
			$pdo->beginTransaction();
			$pdo->_update($prod_table, $set_primaryImgData, $set_updateimgBy);
			$pdo->endTransaction();*/
		}
		$_SESSION['rand_idads'] = "";
		/**** add/update variation ***/
		$variation_count = 0;
		$pdo->beginTransaction();

		$sel = mysqlQuery("DELETE FROM ab_product_variation_unit WHERE abpdu_product_id = '".$res_ins_id."'");
		$extension=array("jpeg","jpg","png","gif");

		foreach($_FILES["variation_file"]["tmp_name"] as $key=>$tmp_name) {
		    $file_name=$_FILES["variation_file"]["name"][$key];
		    $file_tmp=$_FILES["variation_file"]["tmp_name"][$key];
		    $ext=pathinfo($file_name,PATHINFO_EXTENSION);
		     $imageId = 0;
		    if($file_name==''){
		    	 $imageId = $_POST['old_image_id'][$key];
		    }
		    if(in_array($ext,$extension)) { 
            $filename=basename($file_name,$ext);
            $newFileName=rand().time().".".$ext;
            move_uploaded_file($file_tmp,"images/prod_image/".$newFileName);
            $resizeObj = new resize('images/prod_image/'.$newFileName);
				// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
				$resizeObj -> resizeImage(222, 255, 'auto');
				// *** 3) Save image ('image-name', 'quality [int]')
				$resizeObj -> saveImage('images/prod_image/thumb/thumb222255_'.$newFileName, 100);
				// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
				$resizeObj -> resizeImage(100, 125, 'auto');
				// *** 3) Save image ('image-name', 'quality [int]')
				$resizeObj -> saveImage('images/prod_image/thumb/thumb_'.$newFileName, 100);
				// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
				$resizeObj -> resizeImage(248, 242, 'auto');
				// *** 3) Save image ('image-name', 'quality [int]')
				$resizeObj -> saveImage('images/prod_image/thumb/thumb248242_'.$newFileName, 100);
				$sel_prod_img = mysqlFetchArray(mysqlQuery("SELECT abpi_order_no FROM ab_product_image WHERE abpi_product_id = '".$res_ins_id."' ORDER BY abpi_id DESC LIMIT 1"));
				$img_order_no = $sel_prod_img['abpi_order_no'] + 1;
				$imageData = array(
					'abpi_product_id' => $res_ins_id,
					'abpi_image' => 'prod_image/'.$newFileName
				);
				if($_POST['old_image_id'][$key]!=0){
					$sel = mysqlFetchArray(mysqlQuery("SELECT abpi_image FROM ab_product_image WHERE abpi_id = '".$_POST['old_image_id'][$key]."' "));
					unlink('images/'.$sel['abpi_image']);
					$del = mysqlQuery("DELETE FROM ab_product_image WHERE abpi_id = '".$_POST['old_image_id'][$key]."'");
				}
				$abpi_img = 'prod_image/'.$newFileName;
				$ins_img = "INSERT INTO ab_product_image SET abpi_product_id = '".$res_ins_id."', abpi_image = '".$abpi_img."', abpi_order_no = '".$img_order_no."' ";
				mysqlQuery($ins_img);
				$imageId = mysqlLastId();
				//$imageId = $pdo->_insert('ab_product_image', $imageData);  
				//unlink('images/'.$newFileName);
		   }
		   if(strtolower($ext)=='webp'){

		   	$filename=basename($file_name,$ext);
            $newFileName=rand().time().".".$ext;
            move_uploaded_file($file_tmp,"images/prod_image/".$newFileName);

		   	$mainimage = 'images/prod_image/'.$newFileName;
				$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb222255_'.$newFileName, 255);
				$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb248242_'.$newFileName,242);
				$images_0 = newimages($mainimage,90,TRUE,NULL,'images/prod_image/thumb/','thumb_'.$newFileName,125);

				$sel_prod_img = mysqlFetchArray(mysqlQuery("SELECT abpi_order_no FROM ab_product_image WHERE abpi_product_id = '".$res_ins_id."' ORDER BY abpi_id DESC LIMIT 1"));
				$img_order_no = $sel_prod_img['abpi_order_no'] + 1;
				$imageData = array(
					'abpi_product_id' => $res_ins_id,
					'abpi_image' => 'prod_image/'.$newFileName
				);
				if($_POST['old_image_id'][$key]!=0){
					$sel = mysqlFetchArray(mysqlQuery("SELECT abpi_image FROM ab_product_image WHERE abpi_id = '".$_POST['old_image_id'][$key]."' "));
					unlink('images/'.$sel['abpi_image']);
					$del = mysqlQuery("DELETE FROM ab_product_image WHERE abpi_id = '".$_POST['old_image_id'][$key]."'");
				}
				$abpi_img = 'prod_image/'.$newFileName;
				$ins_img = "INSERT INTO ab_product_image SET abpi_product_id = '".$res_ins_id."', abpi_image = '".$abpi_img."', abpi_order_no = '".$img_order_no."' ";
				mysqlQuery($ins_img);
				$imageId = mysqlLastId();
				//$imageId = $pdo->_insert('ab_product_image', $imageData);  
				//unlink('images/'.$newFileName);
		   }
		   if($_POST['variation_title'][$key]!='' && $_POST['variation_val'][$key]!=''){
		   	$ins_variation = mysqlQuery('INSERT INTO ab_product_variation_unit SET abpdu_product_id = "'.$res_ins_id.'", abpdu_title="'.$_POST['variation_title'][$key].'",abpdu_value = "'.$_POST['variation_val'][$key].'",abpdu_image_id="'.$imageId.'" ');	
		   }
		}
		foreach ($_POST['abpd_unit'] as $key => $value) {
			$default_var_indx = $variation_count+1;
			$unit = $_POST['abpd_unit'][$variation_count];
			$unit_qty = intval($_POST['abpd_unit_quantity'][$variation_count]);
			// $variation_code = $prod_code."-".$unit_qty."-".$unit;
			$default_variation = $_POST['is_default_variation_'.$default_var_indx];
			$default_variation = ($default_variation == '') ? 'N' : $default_variation;
			$delhivery = $_POST['delhivery_'.$default_var_indx];
			//echo $default_var_indx.'--'.$_POST['delhivery_'.$default_var_indx].'<br>';
			$delhivery = ($delhivery == '') ? 'N' : $delhivery;
			$variation_color = isset($_POST['abpdv_variation_color_'.$default_var_indx]) ? implode(',',$_POST['abpdv_variation_color_'.$default_var_indx]) : '';
			$abpdv_qty = $_POST['abpd_qty'][$variation_count];
			$old_abpd_qty = $_POST['old_abpd_qty'][$variation_count];
			if($abpd_out_of_stock == 'Y'){
				$abpdv_in_stock = 'N';
			}else{
				$abpdv_in_stock = ( $abpdv_qty > 0 ) ? 'Y' : 'N';
			}
			$old_abpdv_in_stock = $_POST['old_abpd_in_stock'][$variation_count];
			$new_abpdv_in_stock = $_POST['new_abpd_in_stock'][$variation_count];
			$notifiy_abpd_var_id = $_POST['abpdv_id'][$variation_count];
			# Generate in stock entry if product variant is out of stock
			if( $abpdv_qty >= $old_abpd_qty && $abpdv_qty > 0 && $old_abpd_qty == 0 ) {
				$tmpFieldsStr = $tmpValuesStr = '';
				$insertNewStockArr = [
					'apo_prod_id' => $abpd_id,
					'apo_prod_vari_id' => $notifiy_abpd_var_id,
					'apo_prod_entry_for' => 'in_stock_entry',
					'apo_date' => $time_stamp,
				];
				foreach( $insertNewStockArr as $tmpK1 => $tmpV1 ) {
					$tmpFieldsStr .= "`".$tmpK1."`, ";
					$tmpValuesStr .= "'".$tmpV1."', ";
				}
				$qry = "INSERT INTO `".$tbl_product_out_of_stock."` (".trim($tmpFieldsStr, ', ').") VALUES (".trim($tmpValuesStr, ', ').") ";
				mysqlQuery($qry);
			}
			if( ($old_abpdv_in_stock != $new_abpdv_in_stock) && $old_abpdv_in_stock == 'N' && $new_abpdv_in_stock == 'Y'){
				$notifiy_abpd_id = $res_ins_id;
				$userstonotifiy = "SELECT id, ab_notify_user_contact FROM ab_notify_user LEFT JOIN ab_product ON abpd_id = ab_notify_product_id WHERE ab_notify_product_id = '".$notifiy_abpd_id."' AND ab_notify_product_var_id = '".$notifiy_abpd_var_id."' AND sent_status = 'N'";
				$userstonotifiyRes = mysqlQuery($userstonotifiy);
				$prodName = $_POST['abpd_name'];
				$metaTitle = $_POST['abpd_meta_title'];
				$prod_foler = "products/";
				$prod_meta_url = str_replace(" ", "_",strtolower(clean($metaTitle))); 
				$product_url = "www.agribegri.com"; //$SITE_TITLE_URL.$prod_foler.$prod_meta_url;
				if(mysqlNumRow($userstonotifiyRes) ){
					while($user = mysqlFetchArray($userstonotifiyRes)){
						$user_record[] = $user['id']; 
						// UPDATE NOTIFY STATUS
						$update_notify_status = mysqlQuery('UPDATE ab_notify_user SET sent_status = "Y" WHERE id = "'.$user['id'].'"');
						//$message = "Thank you for interesting in product ".$prodName." is now available on www.agribegri.com! You can order it on".$product_url;
						$message = "We would glad to inform you that ".substr($prodName,0,25)." is now available on cheap Agro Store www.agribegri.com. You can see it on ".$product_url." . ";
						$message = urlencode($message);
						$mobile = $user['ab_notify_user_contact'];
						//if(IS_BETA_ENVIRONEMENT=='N'){
							/*$ch = curl_init("http://smshorizon.co.in/api/sendsms.php?user=".$USER_NAME_API."&apikey=".$API_KEY."&mobile=".$mobile."&message=".$message."&senderid=".$SENDER_ID."&type=".$TYPE_KEY."&tid=1207161536746225353"); 
							curl_setopt($ch, CURLOPT_HEADER, 0);
							curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
							$output = curl_exec($ch);      
							curl_close($ch);*/
						//}
					}
				}
			}
			$variation_code = generate_variation_code($prod_code,$unit,$unit_qty,$variation_count+1);
			$abpdv_ac_quantity = ($_POST['abpdv_ac_quantity'][$variation_count] != '') ? $_POST['abpdv_ac_quantity'][$variation_count] : '0';
			if($_POST['abpdv_seller_id'][$variation_count]!=''){
				$abpdv_seller_id = $_POST['abpdv_seller_id'][$variation_count];
			}else{
				$abpdv_seller_id = 0;
			}
			$unit_arr = array('gm','kg','liter','ml');
			if(in_array(trim($unit), $unit_arr)){ $abpdv_notes = mysqlRealescapestring($_POST['abpdv_notes'][$variation_count]); }else{ $abpdv_notes = mysqlRealescapestring($_POST['abpdv_notes_txt'][$variation_count]); }
        
        	$var_new_price = floatval($_POST['abpd_price'][$variation_count]);
        	$var_new_shipped_by = $_POST['shipped_by'][$variation_count] ? $_POST['shipped_by'][$variation_count] : 'Agribegri';
        	$var_new_qty = intval($_POST['abpd_qty'][$variation_count]);
        	$abpdv_id = $_POST['abpdv_id'][$variation_count];
        	
        	$old_price = isset($_POST['abpdv_old_price'][$variation_count]) ? floatval($_POST['abpdv_old_price'][$variation_count]) : "";
        	$old_shipped_by = isset($_POST['abpdv_old_shipped_by'][$variation_count]) ? $_POST['abpdv_old_shipped_by'][$variation_count] : "Agribegri";
        	$old_qty = isset($_POST['abpdv_old_qty'][$variation_count]) ? $_POST['abpdv_old_qty'][$variation_count] : "";
        	
        	$prodLogInsdata = array();
            if($old_price != $var_new_price)
            {
            	$prodLogInsdata[$abpdv_id]['abpdvc_change_price'] = 1;
            }
        	if($old_shipped_by != $var_new_shipped_by)
            {
            	$prodLogInsdata[$abpdv_id]['abpdvc_change_shipped_by'] = 1;
            }
        	if($old_qty != $var_new_qty)
            {
            	$prodLogInsdata[$abpdv_id]['abpdvc_change_qty'] = 1;
            }
        
			$variation_data = array(
				'abpdv_abpd_id' => $res_ins_id,
				'abpdv_code' => $variation_code,
				'abpdv_unit' => $unit,
				'abpdv_unit_quantity' => floatval($_POST['abpd_unit_quantity'][$variation_count]),
				'abpdv_price' => floatval($_POST['abpd_price'][$variation_count]),
				'abpdv_discount' => floatval($_POST['abpd_discount'][$variation_count]),
				'abpdv_sale_price' => round(($_POST['abpd_price'][$variation_count]) - ($_POST['abpd_discount'][$variation_count])),
				'abpdv_shipping_charge' => floatval($_POST['abpd_shipping_charge'][$variation_count]),
				'abpdv_today_offer' => $_POST['abpd_today_offer'][$variation_count],
				'abpdv_today_discount' => floatval($_POST['abpd_today_discount'][$variation_count]),
				'abpdv_mrp_discount' => (isset($_POST['abpdv_mrp_discount'][$variation_count])) ? intval($_POST['abpdv_mrp_discount'][$variation_count]) : '0',
				'abpdv_till_date' => (isset($_POST['abpd_till_date'][$variation_count]) && $_POST['abpd_till_date'][$variation_count]!='' && $_POST['abpd_till_date'][$variation_count]!='0000-00-00') ? $_POST['abpd_till_date'][$variation_count] : '1000-01-01',
				'abpdv_till_time' => $_POST['abpd_till_time'][$variation_count],
				'abpdv_local_store_avail' => (isset($_POST['abpd_local_store_avail'][$variation_count])) ? $_POST['abpd_local_store_avail'][$variation_count] : 'N',
				'abpdv_local_store_dist' => (isset($_POST['abpd_local_store_dist'][$variation_count])) ? floatval($_POST['abpd_local_store_dist'][$variation_count]) : '0.00',
				'abpdv_alert_quantity' => intval($_POST['abpd_alert_quantity'][$variation_count]),
				'abpdv_qty' => intval($_POST['abpd_qty'][$variation_count]),
				'abpdv_no_boxes' => intval($_POST['abpdv_no_boxes'][$variation_count]),
				'abpdv_weight_packing' => floatval($_POST['abpd_weight_packing'][$variation_count]),
				'abpdv_post_charge' => floatval($_POST['abpd_post_charge'][$variation_count]),
				'abpdv_post_charge_without_extra' => floatval($_POST['abpdv_post_charge_without_extra'][$variation_count]),
				'abpdv_post_charge_extra' => floatval($_POST['abpdv_post_charge_extra'][$variation_count]),
				'abpdv_in_stock' => $abpdv_in_stock,
				'abpdv_min_order_qty' => intval($_POST['abpd_min_order_qty'][$variation_count]),
				'abpdv_new_arrival_product' => $_POST['abpd_new_arrival_product'][$variation_count],
				'abpdv_display_on_home' => $_POST['abpd_display_on_home'][$variation_count],
				'abpdv_minimum_dealer_qty' => intval($_POST['abpd_minimum_dealer_qty'][$variation_count]),
				'abpdv_minimum_order_qty_discount' => intval($_POST['abpd_minimum_order_qty_discount'][$variation_count]),
				'abpdv_shipped_by' => $_POST['shipped_by'][$variation_count] ? $_POST['shipped_by'][$variation_count] : 'Agribegri',
				'abpdv_seller_amount' => floatval($_POST['abpd_seller_amount'][$variation_count]),
				'abpdv_length' => floatval($_POST['abpd_length'][$variation_count]),
				'abpdv_width' => floatval($_POST['abpd_width'][$variation_count]),
				'abpdv_height' => floatval($_POST['abpd_height'][$variation_count]),
				'abpdv_volumetric_weight' => floatval($_POST['abpd_volumetric_weight'][$variation_count]),
				'abpdv_product_on_sale' => (isset($_POST['abpd_product_on_sale'][$variation_count]) && $_POST['abpd_product_on_sale'][$variation_count]!='') ? $_POST['abpd_product_on_sale'][$variation_count] : 'N',
				'abpdv_display_order' => intval($_POST['abpdv_display_order'][$variation_count]),
				'abpdv_notes' => $abpdv_notes,
				'abpdv_ac_name' => mysqlRealescapestring($_POST['abpdv_ac_name'][$variation_count]),
				'abpdv_ac_quantity' => intval($abpdv_ac_quantity),
				'abpdv_shipping_through' => $_POST['abpdv_shipping_through'][$variation_count],
				'abpdv_payment_method' => $_POST['abpdv_payment_method'][$variation_count] ? $_POST['abpdv_payment_method'][$variation_count] : 'both',
				'abpdv_status' => $_POST['abpdv_status'][$variation_count],
				'abpdv_display_for' => $_POST['abpdv_display_for'][$variation_count],
				'abpdv_is_default' => $default_variation,
				'abpdv_sku_use' => $_POST['abpdv_sku_use'][$variation_count],
				'abpdv_sku_other_code' => $_POST['abpdv_other_sku'][$variation_count],
				'abpdv_sku_other_qty' => ($_POST['abpdv_other_qty'][$variation_count] != '') ? $_POST['abpdv_other_qty'][$variation_count] : 0,
				'abpdv_rate_compare' => floatval($_POST['abpdv_rate_compare'][$variation_count]),
				'abpdv_variation_color' => $variation_color,
				'abpdv_delhivery' => $delhivery,
				'abpdv_seller_id' => $abpdv_seller_id,
				'abpdv_exp_date' => (isset($_POST['abpdv_exp_date'][$variation_count]) && $_POST['abpdv_exp_date'][$variation_count]!='' && $_POST['abpdv_exp_date'][$variation_count]!='0000-00-00') ? $_POST['abpdv_exp_date'][$variation_count] : '1000-01-01',
				'abpdv_fulfilment_amount' => floatval($_POST['abpdv_fulfilment_amount'][$variation_count]),
			);
			//echo "<pre>"; print_r($variation_data);
			if($_REQUEST['debug']!=""){
				//print_r($variation_data); exit;
			}
			// $abpdv_id = $_POST['abpdv_id'][$variation_count];
			// echo '<pre>'; print_r($variation_data);
			//send mail and message for stock over start
			$abpdv_alert_quantity = $_POST['abpd_alert_quantity'][$variation_count];
			$abpdv_old_qty = $_POST['old_abpd_qty'][$variation_count];
			if(($abpdv_qty!=$abpdv_old_qty) && ($abpdv_qty <= $abpdv_alert_quantity) && ($abpd_active=="Y")){
				//send_alert_qty_mail_msg($abpd_name,$abpd_meta_title);
			}
			if(($abpdv_qty!=$abpdv_old_qty) && ($abpdv_qty<=0 && $abpd_active=="Y")){
				$out_of_stock_data = array('prod_id'=>$res_ins_id,'prod_vari_id'=>$abpdv_id);
				send_outof_stock_mail_msg($abpd_name,$abpd_meta_title,'',$out_of_stock_data);
			}
			//send mail and message for stock over end
			if($abpdv_id!='' && $abpdv_id>0){
				$old_sell_price_qry = mysqlQuery("SELECT abpdv_sale_price,abpdv_seller_id,abpdv_qty FROM ab_product_variation WHERE abpdv_id = '".$abpdv_id."' ");
				$old_sell_price_res = mysqlFetchArray($old_sell_price_qry);
				$old_sale_price = $old_sell_price_res['abpdv_sale_price'];
				$old_seller_id = $old_sell_price_res['abpdv_seller_id'];
				$old_qty = $old_sell_price_res['abpdv_qty'];
				generate_prod_vari_change_log($abpdv_id, $_SESSION['admin_id'], 'AdminPanel', $prodLogInsdata);
				/*if(in_array($_GET['seller_id'],$seller_skip_Arr)){
					unset($variation_data['abpdv_sale_price']);
					unset($variation_data['abpdv_post_charge_without_extra']);
					unset($variation_data['abpdv_post_charge_extra']);
					unset($variation_data['abpdv_seller_amount']);
					unset($variation_data['abpdv_post_charge']);
				}*/
				if($_FILES["abpdv_variation_img"]["name"][$variation_count]!=''){
					$extension=array("jpeg","jpg","png","gif","JPEG","JPG","PNG","GIF");
					//foreach($_FILES["abpdv_variation_img"]["tmp_name"] as $key=>$tmp_name) {
					   $file_name=$_FILES["abpdv_variation_img"]["name"][$variation_count];
					   $file_tmp=$_FILES["abpdv_variation_img"]["tmp_name"][$variation_count];
					   $ext=pathinfo($file_name,PATHINFO_EXTENSION);
					   $imageId = 0;
					   if($file_name==''){
					    	$imageId = $_POST['old_variation_image_id'][$variation_count];
					   }
					   if(in_array($ext,$extension)) { 
			            $filename=basename($file_name,$ext);
			            $newFileName=rand().time().".".$ext;
			            move_uploaded_file($file_tmp,"images/prod_image/".$newFileName);
			            $resizeObj = new resize('images/prod_image/'.$newFileName);
							// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
							$resizeObj -> resizeImage(222, 255, 'auto');
							// *** 3) Save image ('image-name', 'quality [int]')
							$resizeObj -> saveImage('images/prod_image/thumb/thumb222255_'.$newFileName, 100);
							// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
							$resizeObj -> resizeImage(100, 125, 'auto');
							// *** 3) Save image ('image-name', 'quality [int]')
							$resizeObj -> saveImage('images/prod_image/thumb/thumb_'.$newFileName, 100);
							// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
							$resizeObj -> resizeImage(248, 242, 'auto');
							// *** 3) Save image ('image-name', 'quality [int]')
							$resizeObj -> saveImage('images/prod_image/thumb/thumb248242_'.$newFileName, 100);
							$sel_prod_img = mysqlFetchArray(mysqlQuery("SELECT abpi_order_no FROM ab_product_image WHERE abpi_product_id = '".$res_ins_id."' ORDER BY abpi_id DESC LIMIT 1"));
							$img_order_no = $sel_prod_img['abpi_order_no'] + 1;
							$imageData = array(
								'abpi_product_id' => $res_ins_id,
								'abpi_image' => 'prod_image/'.$newFileName
							);
							if($_POST['old_variation_image_id'][$variation_count]!=0){
								$sel = mysqlFetchArray(mysqlQuery("SELECT abpi_image FROM ab_product_image WHERE abpi_id = '".$_POST['old_variation_image_id'][$variation_count]."' "));
								unlink('images/'.$sel['abpi_image']);
								$del = mysqlQuery("DELETE FROM ab_product_image WHERE abpi_id = '".$_POST['old_variation_image_id'][$variation_count]."'");
							}
							$abpi_img = 'prod_image/'.$newFileName;
							$ins_img = "INSERT INTO ab_product_image SET abpi_product_id = '".$res_ins_id."', abpi_image = '".$abpi_img."', abpi_order_no = '".$img_order_no."' ";
							mysqlQuery($ins_img);
							$imageId = mysqlLastId();
							//$imageId = $pdo->_insert('ab_product_image', $imageData);  
							$variation_data['abpdv_variation_img'] = $imageId;
							//unlink('images/'.$newFileName);
							//echo 'UPDATE '.$variation_table.' SET abpdv_variation_img = '.$imageId.' WHERE abpdv_id = '.$last_ins_id ; exit;
					   	//$upd = mysqlQuery('UPDATE '.$variation_table.' SET abpdv_variation_img = '.$imageId.' WHERE abpdv_id = '.$last_ins_id .' ');
					   }
					//}
				}
				$updateStr = '';
				foreach( $variation_data as $tmpK => $tmpV ) {
					$updateStr .= " `".$tmpK."` = '".$tmpV."', ";
				}
				$updateStr = trim($updateStr, ', ');
				$qry = "UPDATE `".$variation_table."` SET ".$updateStr." WHERE `abpdv_id` = ".$abpdv_id;
				$res = mysqlQuery($qry);
				if($_POST['product_code'][$variation_count]!=''){
					//$check_var_code = mysqlQuery("SELECT abpdv_id FROM ab_product_variation v JOIN ab_product p ON v.abpdv_abpd_id = p.abpd_id  WHERE v.abpdv_code = '".$variation_code."' AND p.abpd_absupplier_id = '".$abpd_absupplier_id."' AND abpdv_id !='".$abpdv_id."' ");
					//if(mysqlNumRow($check_var_code)==0){
						$sel = mysqlFetchArray(mysqlQuery("SELECT abpd_id FROM ab_product WHERE abpd_code LIKE '%".trim($_POST['product_code'][$variation_count])."' "));
						$ins = mysqlQuery("INSERT INTO `".$variation_table."` SET ".$updateStr);
						$last_var_ins_id = mysqlLastId();
						fastrr_product_update_new($sel['abpd_id']);
						//$last_var_ins_id = mysql_insert_id();
						//echo "INSERT INTO `".$variation_table."` SET ".$updateStr; exit;
						//$last_var_ins_id = $pdo->_insert($variation_table, $variation_data);
						$upd = "UPDATE `".$variation_table."` SET abpdv_abpd_id = '".$sel['abpd_id']."', abpdv_is_default='N', abpdv_status='N', abpdv_display_order = 0, abpdv_code='' WHERE abpdv_id = '".$last_var_ins_id."'  ";
						$res_upd = mysqlQuery($upd);
					//}
				}
				/*if($sale_price!=$_POST['abpdv_old_sale_price'][$variation_count] || $_POST['abpd_name']!=$_POST['abpd_old_name'] || $_POST['abpd_cat_id']!=$_POST['abpd_old_cat_id'] || $_POST['abpdv_unit'][$variation_count]!=$_POST['abpdv_old_unit'][$variation_count] || $_POST['abpdv_unit_quantity'][$variation_count]!=$_POST['abpdv_old_unit_quantity'][$variation_count] || $_POST['abpdv_status'][$variation_count]!=$_POST['abpdv_old_status'][$variation_count] || $abpd_original_status!=$abpd_active){
					//echo $res_ins_id; exit;
					fastrr_product_update_new($res_ins_id);	
				}*/
				//echo "OLD".$abpd_multi_seller_product.'<br>';
				if($abpd_multi_seller_product=='Y'){
					Activitylog('Min Sale Price product - admin','Sale price: '.$old_sale_price.'==> Seller id: '.$old_seller_id." Qty = '".$old_qty."' New Sale Price = '".$_POST['abpd_sale_price'][$variation_count]."' abpdv_id = '".$abpdv_id."'", $_SESSION['admin_id'],'admin');


					$sel_multi = mysqlQuery("SELECT id FROM ab_multiseller_product WHERE seller_id = '".$abpd_absupplier_id."' AND product_id = '".$res_ins_id."' AND variation_id = '".$abpdv_id."' ");
					if(mysqlNumRow($sel_multi) == 0){
						//echo "<pre>"; print_r($_POST); exit;
						$mrp_discount = 0;
						if(isset($_POST['abpdv_mrp_discount'][$variation_count])){
							$mrp_discount = $_POST['abpdv_mrp_discount'][$variation_count];
						}else{
							$mrp_discount = 0;
						}
						$ins = mysqlQuery("INSERT INTO ab_multiseller_product SET seller_id = '".$abpd_absupplier_id."',
																	product_id = '".$res_ins_id."',
																	variation_id = '".$abpdv_id."',
																	price = '".floatval($_POST['abpd_price'][$variation_count])."',
																	discount_rs = '".floatval($_POST['abpd_discount'][$variation_count])."',
																	sale_price = '".round($_POST['abpd_sale_price'][$variation_count])."', 
																	mrp_discount = '".$mrp_discount."',
																	seller_amount = '".floatval($_POST['abpd_seller_amount'][$variation_count])."',
																	qty = '".intval($_POST['abpd_qty'][$variation_count])."',
																	courier_charge = '".floatval($_POST['abpdv_post_charge_without_extra'][$variation_count])."',
																	cod = '".floatval($_POST['abpdv_post_charge_extra'][$variation_count])."',
																	abpdv_post_charge = '".floatval($_POST['abpd_post_charge'][$variation_count])."',
																	notes = '".$abpdv_notes."'");
					}else{


						$sel_multi_qry = mysqlQuery("SELECT * FROM ab_multiseller_product WHERE seller_id != '".$abpdv_seller_id."' AND product_id = '".$res_ins_id."' AND variation_id = '".$abpdv_id."' ");
						if(mysqlNumRow($sel_multi_qry) > 0){
							while($sel_multi_qry_res = mysqlFetchArray($sel_multi_qry)){
								//Calculate COD & Courier
								$abpd_weight = round($_POST['abpd_weight_packing'][$variation_count]);
								$abpd_weight_packing = $abpd_weight;
								$abpd_sale_price = round($sel_multi_qry_res['sale_price']);
								$courier_charge =0;
								$cod_charge_per = COD_CHARGE_PER;
								$cod_charge_above_twenty = COD_CHARGE_ABOVE_TWENTY;
								$cod_fixed_charge = COD_FIXED_CHARGE;
								$abpdv_post_charge_without_extra =0;
								$abpdv_post_charge_extra =0;
								$payment_method = $_POST['abpdv_payment_method'][$variation_count];
								$shipped_by = $_POST['shipped_by'][$variation_count];
								$delhivery_check = $delhivery;
								if($delhivery_check=='Y'){
									if($abpd_weight_packing <=500){
										$courier_charge = $abpdv_post_charge_without_extra = 50.00;
									}else if($abpd_weight_packing >500 && $abpd_weight_packing <=1000){
										$courier_charge = $abpdv_post_charge_without_extra = 70.00;
									}else if($abpd_weight_packing >1000 && $abpd_weight_packing <=2000){
										$courier_charge = $abpdv_post_charge_without_extra = 100.00;
									}else if($abpd_weight_packing >2000 && $abpd_weight_packing <=3000){
										$courier_charge = $abpdv_post_charge_without_extra = 140.00;
									}else if($abpd_weight_packing >3000 && $abpd_weight_packing <=5000){
										$courier_charge = $abpdv_post_charge_without_extra = 200.00;
									}else if($abpd_weight_packing >5000 && $abpd_weight_packing <=6000){
										$courier_charge = $abpdv_post_charge_without_extra = 265.00;
									}else if($abpd_weight_packing >6000 && $abpd_weight_packing <=7000){
										$courier_charge = $abpdv_post_charge_without_extra = 310.00;
									}else if($abpd_weight_packing >7000 && $abpd_weight_packing <=8000){
										$courier_charge = $abpdv_post_charge_without_extra = 355.00;
									}else if($abpd_weight_packing >8000 && $abpd_weight_packing <=10000){
										$courier_charge = $abpdv_post_charge_without_extra = 400.00;
									}else if($abpd_weight_packing >10000){
										$get_kg = ceil($abpd_weight_packing/1000);
										$per_kg_charge = ($per_kg*$cod_charge_above_twenty);
										$courier_charge = $abpdv_post_charge_without_extra = 400.00+$per_kg_charge;
									}
								}else{
									if($abpd_weight_packing <=500){
										$courier_charge = $abpdv_post_charge_without_extra = 80.00;
									}else if($abpd_weight_packing >500 && $abpd_weight_packing <=1000){
										$courier_charge = $abpdv_post_charge_without_extra = 112.00;
									}else if($abpd_weight_packing >1000 && $abpd_weight_packing <=2000){
										$courier_charge = $abpdv_post_charge_without_extra = 140.00;
									}else if($abpd_weight_packing >2000 && $abpd_weight_packing <=5000){
										$courier_charge = $abpdv_post_charge_without_extra = 220.00;
									}else if($abpd_weight_packing >5000 && $abpd_weight_packing <=6000){
										$courier_charge = $abpdv_post_charge_without_extra = 265.00;
									}else if($abpd_weight_packing >6000 && $abpd_weight_packing <=7000){
										$courier_charge = $abpdv_post_charge_without_extra = 310.00;
									}else if($abpd_weight_packing >7000 && $abpd_weight_packing <=8000){
										$courier_charge = $abpdv_post_charge_without_extra = 355.00;
									}else if($abpd_weight_packing >8000 && $abpd_weight_packing <=10000){
										$courier_charge = $abpdv_post_charge_without_extra = 400.00;
									}else if($abpd_weight_packing >10000){
										$get_kg = ceil($abpd_weight_packing/1000);
										$per_kg = $get_kg-10;//above_twenty/1000;
										$per_kg_charge = ($per_kg*$cod_charge_above_twenty);
										$courier_charge = $abpdv_post_charge_without_extra = 400.00+$per_kg_charge;
									}
								}
								if($payment_method=='online')
								{
									$courier_per_charge = 	$abpd_sale_price * (2 / 100);
									$courier_extra_charge = $courier_per_charge;//Math.max(courier_per_charge, cod_fixed_charge);
									$courier_charge = $courier_charge+$courier_extra_charge;
									$courier_charge = floatval($courier_charge);
									$abpdv_post_charge_extra = floatval($courier_extra_charge);
								}else{
									if($abpd_sale_price <=1500){
										$courier_charge = $courier_charge+$cod_fixed_charge;
										$courier_charge = floatval($courier_charge);
										$abpdv_post_charge_extra = floatval($cod_fixed_charge);	
									}else if($abpd_sale_price >1500){
										$courier_per_charge = $abpd_sale_price*($cod_charge_per/100);
										$courier_extra_charge = max($courier_per_charge, $cod_fixed_charge);
										$courier_charge = $courier_charge+$courier_extra_charge;
										$courier_charge = floatval($courier_charge);
										$abpdv_post_charge_extra = floatval($courier_extra_charge);
									}
								}
								$abpdv_post_charge_without_extra = floatval($abpdv_post_charge_without_extra);
								$abpdv_shipping_through = $_POST['abpdv_shipping_through'][$variation_count];
								if($abpdv_shipping_through!='Shipping Through Transport'){
									if($shipped_by == 'Self'){
										$abpd_post_charge = 0.00;
										$abpdv_post_charge_without_extra = 0.00;
										$abpdv_post_charge_extra = 0.00;
									}else{
										$abpd_post_charge = $courier_charge;
										$abpdv_post_charge_without_extra = $abpdv_post_charge_without_extra;
										$abpdv_post_charge_extra = $abpdv_post_charge_extra;
									}
								}else{
									$abpd_post_charge = 0.00;
									$abpdv_post_charge_without_extra = 0.00;
									$abpdv_post_charge_extra = 0.00;
								}
								$seller_id_arr = json_encode($seller_skip_Arr);
								$seller_id = $sel_multi_qry_res['seller_id'];
								$price = $sel_multi_qry_res['price'];
								$price = ($price == '') ? 0 : $price;
								$discount = $sel_multi_qry_res['discount_rs'];
								$today_offer_avail = $_POST['abpd_today_offer'][$variation_count];
								if($today_offer_avail == 'Y'){
									$discount = floatval($_POST['abpd_today_discount'][$variation_count]);
								}
								$discount = ($discount == '') ? 0 : $discount;
								$saleprice = (floatval($price)  - floatval($discount));
								//if(!seller_id_arr.includes(seller_id)){
									$abpd_sale_price = $saleprice;
								//}
								/*** SELLER WILL GET AMOUNT CALCULATION ***/
								$shipped_by = $_POST['shipped_by'][$variation_count];
								$postal_charge = $abpd_post_charge;
								$shipping_commission = '0';
								//$shipped_by_commissionJSON = json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT);
								$sellerwillget = 0;
								$gst_product = 0;
								$total_commission = 0;
								foreach ($agribegriCommisson_Arr as $key => $value) {
									if($shipped_by == $key){
										$shipping_commission = $value;
									}
								}
								if($shipped_by != ''){
									$sellerwillget = (($saleprice*100)/(100+floatval($gst_product))) - ($saleprice*$total_commission/100) - (($saleprice*$shipping_commission)/100) - $postal_charge;
								}
								if($shipped_by == 'Self'){
								   $sellerwillget = $saleprice - ($saleprice*($shipping_commission/100));
								   $abpd_seller_amount = floatval($sellerwillget);
								}else{
									$abpd_seller_amount = floatval($sellerwillget);
								}
								$upd = mysqlQuery("UPDATE ab_multiseller_product SET 
																	seller_amount = '".floatval($abpd_seller_amount)."',
																	courier_charge = '".floatval($abpdv_post_charge_without_extra)."',
																	cod = '".floatval($abpdv_post_charge_extra)."',
																	abpdv_post_charge = '".floatval($abpd_post_charge)."' WHERE id = '".$sel_multi_qry_res['id']."' ");
							}
						}
						$sel_pro = mysqlQuery("SELECT * FROM ab_multiseller_product WHERE seller_id = '".$abpdv_seller_id."' AND product_id = '".$res_ins_id."' AND variation_id = '".$abpdv_id."' ");
						if(mysqlNumRow($sel_pro) > 0){
							$sel_res = mysqlFetchArray($sel_pro);
							if(intval($_POST['abpd_qty'][$variation_count]) <= 0){
								$cur_date = date('Y-m-d H:i:s');
								$upd=mysqlQuery("UPDATE ab_multiseller_product SET out_of_stock_date = '".$cur_date."' WHERE id = '".$sel_res['id']."' ");
							}
							$update = "UPDATE ab_multiseller_product SET price = '".$_POST['abpd_price'][$variation_count]."',
																	discount_rs = '".$_POST['abpd_discount'][$variation_count]."',
																	sale_price = '".round($_POST['abpd_sale_price'][$variation_count])."', 
																	mrp_discount = '".$_POST['abpdv_mrp_discount'][$variation_count]."',
																	seller_amount = '".$_POST['abpd_seller_amount'][$variation_count]."',
																	qty = '".intval($_POST['abpd_qty'][$variation_count])."',
																	courier_charge = '".$_POST['abpdv_post_charge_without_extra'][$variation_count]."',
																	cod = '".$_POST['abpdv_post_charge_extra'][$variation_count]."',
																	abpdv_post_charge = '".$_POST['abpd_post_charge'][$variation_count]."',
																	notes = '".$abpdv_notes."'
									WHERE id = '".$sel_res['id']."' ";
							mysqlQuery($update);
						}
					}
					//GET MIN Sell Price AND Update
					update_min_sale_price($res_ins_id,$abpdv_id,'Min Sale Price update product - admin',$_SESSION['admin_id'],'admin',$old_sale_price,$old_seller_id);
				}
				if($_REQUEST['debug']!=""){
					echo "<br>U: ".$qry; exit;
				}
				// $vari_updateBy = array( 'abpdv_id' => $abpdv_id);
				// $variation_res = $pdo->_update($variation_table, $variation_data, $vari_updateBy);
			}else{
				if($_FILES["abpdv_variation_img"]["name"][$variation_count]!=''){
					$extension=array("jpeg","jpg","png","gif","JPEG","JPG","PNG","GIF");
					//foreach($_FILES["abpdv_variation_img"]["tmp_name"] as $key=>$tmp_name) {
					   $file_name=$_FILES["abpdv_variation_img"]["name"][$variation_count];
					   $file_tmp=$_FILES["abpdv_variation_img"]["tmp_name"][$variation_count];
					   $ext=pathinfo($file_name,PATHINFO_EXTENSION);
					   $imageId = 0;
					   // if($file_name==''){
					   //  	$imageId = $_POST['old_image_id'][$key];
					   // }
					   if(in_array($ext,$extension)) { 
			            $filename=basename($file_name,$ext);
			            $newFileName=rand().time().".".$ext;
			            move_uploaded_file($file_tmp,"images/prod_image/".$newFileName);
			            $resizeObj = new resize('images/prod_image/'.$newFileName);
							// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
							$resizeObj -> resizeImage(222, 255, 'auto');
							// *** 3) Save image ('image-name', 'quality [int]')
							$resizeObj -> saveImage('images/prod_image/thumb/thumb222255_'.$newFileName, 100);
							// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
							$resizeObj -> resizeImage(100, 125, 'auto');
							// *** 3) Save image ('image-name', 'quality [int]')
							$resizeObj -> saveImage('images/prod_image/thumb/thumb_'.$newFileName, 100);
							// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
							$resizeObj -> resizeImage(248, 242, 'auto');
							// *** 3) Save image ('image-name', 'quality [int]')
							$resizeObj -> saveImage('images/prod_image/thumb/thumb248242_'.$newFileName, 100);
							$sel_prod_img = mysqlFetchArray(mysqlQuery("SELECT abpi_order_no FROM ab_product_image WHERE abpi_product_id = '".$res_ins_id."' ORDER BY abpi_id DESC LIMIT 1"));
							$img_order_no = $sel_prod_img['abpi_order_no'] + 1;
							$imageData = array(
								'abpi_product_id' => $res_ins_id,
								'abpi_image' => 'prod_image/'.$newFileName
							);
							/*if($_POST['old_image_id'][$key]!=0){
								$sel = mysqlFetchArray(mysqlQuery("SELECT abpi_image FROM ab_product_image WHERE abpi_id = '".$_POST['old_image_id'][$key]."' "));
								unlink('images/'.$sel['abpi_image']);
								$del = mysqlQuery("DELETE FROM ab_product_image WHERE abpi_id = '".$_POST['old_image_id'][$key]."'");
							}*/
							//$imageId = $pdo->_insert('ab_product_image', $imageData);
							$abpi_img = 'prod_image/'.$newFileName;
							$ins_img = "INSERT INTO ab_product_image SET abpi_product_id = '".$res_ins_id."', abpi_image = '".$abpi_img."', abpi_order_no = '".$img_order_no."' ";
							mysqlQuery($ins_img);
							$imageId = mysqlLastId();
							$variation_data['abpdv_variation_img'] = $imageId;
							//unlink('images/'.$newFileName);
							//echo 'UPDATE '.$variation_table.' SET abpdv_variation_img = '.$imageId.' WHERE abpdv_id = '.$last_ins_id ; exit;
					   	//$upd = mysqlQuery('UPDATE '.$variation_table.' SET abpdv_variation_img = '.$imageId.' WHERE abpdv_id = '.$last_ins_id .' ');
					   }
					//}
				}
				$last_ins_id = $pdo->_insert($variation_table, $variation_data);
				if($abpd_multi_seller_product=='Y'){
					$old_sell_price_qry = mysqlQuery("SELECT abpdv_sale_price,abpdv_seller_id FROM ab_product_variation WHERE abpdv_id = '".$last_ins_id."' ");
					$old_sell_price_res = mysqlFetchArray($old_sell_price_qry);
					$old_sale_price = $old_sell_price_res['abpdv_sale_price'];
					$old_seller_id = $old_sell_price_res['abpdv_seller_id'];
					/*echo "SELECT id FROM ab_multiseller_product WHERE seller_id = '".$abpd_absupplier_id."' AND product_id = '".$res_ins_id."' AND variation_id = '".$last_ins_id."' "; exit;*/
					$sel_multi = mysqlQuery("SELECT id FROM ab_multiseller_product WHERE seller_id = '".$abpd_absupplier_id."' AND product_id = '".$res_ins_id."' AND variation_id = '".$last_ins_id."' ");
					if(mysqlNumRow($sel_multi) == 0){
						$mrp_discount = 0;
						if(isset($_POST['abpdv_mrp_discount'][$variation_count])){
							$mrp_discount = $_POST['abpdv_mrp_discount'][$variation_count];
						}else{
							$mrp_discount = 0;
						}
						$ins = mysqlQuery("INSERT INTO ab_multiseller_product SET seller_id = '".$abpd_absupplier_id."',
																	product_id = '".$res_ins_id."',
																	variation_id = '".$last_ins_id."',
																	price = '".floatval($_POST['abpd_price'][$variation_count])."',
																	discount_rs = '".floatval($_POST['abpd_discount'][$variation_count])."',
																	sale_price = '".round($_POST['abpd_sale_price'][$variation_count])."', 
																	mrp_discount = '".$mrp_discount."',
																	seller_amount = '".floatval($_POST['abpd_seller_amount'][$variation_count])."',
																	qty = '".intval($_POST['abpd_qty'][$variation_count])."',
																	courier_charge = '".floatval($_POST['abpdv_post_charge_without_extra'][$variation_count])."',
																	cod = '".floatval($_POST['abpdv_post_charge_extra'][$variation_count])."',
																	abpdv_post_charge = '".floatval($_POST['abpd_post_charge'][$variation_count])."',
																	notes = '".$abpdv_notes."'");
					}
					//GET MIN Sell Price AND Update
					update_min_sale_price($res_ins_id,$last_ins_id,'Min Sale Price add product - admin',$_SESSION['admin_id'],'admin',$old_sale_price,$old_seller_id);
				}
			}
			$variation_count++;
		} //exit;

		if($res_ins_id!='' && $res_ins_id > 0){
			fastrr_product_update_new($res_ins_id);
			//UpdateDefaultStatus($res_ins_id);
        	// SetDefaultVariation($res_ins_id); // set default variation - HJ
		}

		/*$sel = mysqlQuery("SELECT abpdv_in_stock FROM ab_product_variation WHERE abpdv_abpd_id = ".$res_ins_id." AND abpdv_is_default = 'Y' ");
		$sel_res = mysqlFetchArray($sel);
		if($sel_res['abpdv_in_stock']=='N'){
			$upd1 = "UPDATE ab_product_variation SET abpdv_is_default = 'N' WHERE abpdv_abpd_id = ".$res_ins_id." AND abpdv_status = 'Y' ";
			$upd_qry = mysqlQuery($upd1);
			$sel_def_var = "SELECT abpdv_id FROM ab_product_variation WHERE abpdv_abpd_id = ".$res_ins_id." AND abpdv_in_stock = 'Y' AND abpdv_status = 'Y' AND abpdv_sale_price = (SELECT MIN(abpdv_sale_price) FROM ab_product_variation WHERE abpdv_abpd_id = ".$res_ins_id." AND abpdv_in_stock = 'Y' AND abpdv_status = 'Y')"; 
			$sel_def_var_qry = mysqlQuery($sel_def_var);
			$sel_def_var_res = mysqlFetchArray($sel_def_var_qry);
			$upd = "UPDATE ab_product_variation SET abpdv_is_default = 'Y' WHERE abpdv_id = ".$sel_def_var_res['abpdv_id'];
			$upd_qry = mysqlQuery($upd);
		}*/
		$pdo->endTransaction();
		/**** seller mail code goes here *****/
		//exit;
    
		// product is same list 
		$it_1 = ($_POST['same_product_json'] != '') ? json_decode($_POST['same_product_json'], TRUE) : array();
		$it_2 = (!empty($_POST['abpd_same_prod'])) ? $_POST['abpd_same_prod'] : array();
		$result_array_1 = array_diff($it_1,$it_2);
		$result_array_2 = array_diff($it_2,$it_1);
		if(!empty($result_array_1) || !empty($result_array_2)){
			$deleteBy = array(
				'abpsp_product_id' => $res_ins_id
			);
			$pdo->beginTransaction();
			$pdo->_delete("ab_product_same_products", $deleteBy);
        	if(!empty($it_2))
            {
            	foreach($_POST['abpd_same_prod'] as $same_prod ){
					$sameRecmdProdData = array(
						"abpsp_product_id" => $res_ins_id,
						"abpsp_same_product_id" => $same_prod
					);
					$ins_rec = $pdo->_insert('ab_product_same_products', $sameRecmdProdData);
				}
            }
			$pdo->endTransaction();
		}
		// same product delete and add end
		
		// similar product delete and add start
		$is_similar_check = "SELECT * FROM `ab_product_similar_products` WHERE abps_similar_product_id IN(".$res_ins_id.")";
		$pdo->_prepare($is_similar_check);
		$result = $pdo->_resultset();
		if(empty($result)){
			$deleteBy = array(
				'abps_product_id' => $res_ins_id
			);
			$pdo->beginTransaction();
			$pdo->_delete("ab_product_similar_products", $deleteBy);
			foreach($_POST['abpd_similar_prod'] as $similar_prod ){
				$recmdProdData = array(
					"abps_product_id" => $res_ins_id,
					"abps_similar_product_id" => $similar_prod
				);
				$ins_rec = $pdo->_insert('ab_product_similar_products', $recmdProdData);
			}
			$pdo->endTransaction();
			header('location:manage_products.php?msg='.$msg);
		}else{
			// product is similar list 
			$it_1 = json_decode($_POST['similar_product_json'], TRUE);
		    $it_2 = $_POST['abpd_similar_prod'];
		    $result_array_1 = array_diff($it_1,$it_2);
		    $result_array_2 = array_diff($it_2,$it_1);
		    if(!empty($result_array_2)){
				$message_type = 'similar_not_add';
				$abpd_id = $_REQUEST['abpd_id'];
				$seller_id = $_REQUEST['seller_id'];
				$send = '?msg='.$message_type;
				if($abpd_id != ''){
					$send .= '&abpd_id='.$abpd_id;	
				}
				if($seller_id != ''){
					$send .= '&seller_id='.$seller_id;	
				}
				CloseConnection($db);
				header('location:add_prod_variation.php'.$send);
				exit();
			}else{
				CloseConnection($db);
				header('location:manage_products.php?msg='.$msg);
				exit;
		    }
		}
		// similar product delete and add end
	}else{
		CloseConnection($db);
		header('location:manage_products.php?msg=meta_added');
		exit;
	}
}
if($_REQUEST["abpd_id"]=="" && $_SESSION['rand_idads']==""){
    $_SESSION['rand_idads']=rand(999999,99999999);
}
function setBorder($val){
	$style = '';
	if($val == '' || $val=='0'){
		$style = "border:1px solid #FF0000;";	
	}	
	return $style;
}
if($_GET['abpd_id'] > 0){
	$getDataBy = array(
		'abpd_id' => $_GET['abpd_id'],
		'abpd_absupplier_id' => $_GET['seller_id']
	);
	$field_list = 'abpd_id, abpd_cat_id, abpd_subcat_id, abpd_childcat_id, abpd_comp_id, abpd_technical_id,abpd_same_product_id,  abpd_absupplier_id, abpd_user_id, abpd_code, abpd_name, abpd_technical_name, abpd_descri, abpd_image, abpd_active, abpd_gst_no,abpd_hsn_code, abpd_product_tags, seller_sale_status, abpd_meta_title, abpd_new_meta_title, abpd_meta_keyword, abpd_meta_desc,abpd_guildline_doc,abpd_returnable,abpd_video,abpd_product_type,abpd_google_product_category,abpd_google_enable,abpd_facebook_enable,abpd_agribegri_fulfilment, abpd_bharat_agri_margin_per,abpd_having_color,abpd_product_color,abpd_country_id,abpd_cropcat_id,abpd_season_month,abpd_display_transport_variant,abpd_allow_advance_payment,abpd_approve11, abpd_approve11_datetime, abpd_approve20, abpd_approve20_datetime, abpd_multi_seller_product, abpd_google_crawl, abpd_facebook_feed_img,abpd_aplus_img,abpd_farmers_only';
	$product_data = $pdo->get_column_data($prod_table, $field_list, $getDataBy);
	//echo "<pre>"; print_r($product_data); exit;
	$product_data = $product_data[0];
	//variation data
	/*$get_only_default = false;
	$product_data['variations'] = get_prod_variation($_GET['abpd_id'], $get_only_default);*/	
	$variation_qry = "SELECT abpdv_id, abpdv_code, abpdv_unit, abpdv_in_stock, abpdv_qty, abpdv_unit_quantity, abpdv_min_order_qty, abpdv_today_offer, abpdv_till_date, abpdv_till_time, abpdv_product_on_sale, abpdv_price, abpdv_discount, abpdv_today_discount, abpdv_sale_price, abpdv_seller_amount, abpdv_local_store_avail, abpdv_local_store_dist, abpdv_minimum_dealer_qty, abpdv_minimum_order_qty_discount, abpdv_new_arrival_product, abpdv_shipped_by, abpdv_no_boxes, abpdv_weight_packing, abpdv_post_charge,abpdv_post_charge_without_extra,abpdv_post_charge_extra, abpdv_length, abpdv_width, abpdv_height, abpdv_volumetric_weight, abpdv_shipping_charge, abpdv_alert_quantity, abpdv_display_order,abpdv_notes,abpdv_ac_name,abpdv_ac_quantity,abpdv_shipping_through,abpdv_payment_method, abpdv_display_on_home,abpdv_is_default,abpdv_mrp_discount,abpdv_status,abpdv_display_for,abpdv_sku_use,abpdv_sku_other_code,abpdv_sku_other_qty,abpdv_variation_img,abpdv_rate_compare,abpdv_variation_color,abpdv_delhivery,abpdv_seller_id,abpdv_exp_date,abpdv_fulfilment_amount FROM ab_product_variation WHERE abpdv_abpd_id='".$_GET['abpd_id']."' AND (abpdv_status='Y' OR abpdv_status='N')";
	$pdo->_prepare($variation_qry);
	$product_data['variations'] = $pdo->_resultset();
}
$variation_unit = mysqlQuery("SELECT * FROM ab_product_variation_unit WHERE abpdu_product_id = '".$_GET['abpd_id']."' ");
// genereate variation code using ajaxCall
if(isset($_POST['type']) && $_POST['type'] == 'generate_variation_code'){
	$prod_code 	= $_POST['prod_code'];
	$unit 		= $_POST['unit'];
	$unit_qty 	= $_POST['unit_qty'];
	$variation_count = $_POST['count'];
	$variation_code = generate_variation_code($prod_code,$unit,$unit_qty,$variation_count);
	echo $variation_code;
	exit();
}
if(isset($_POST['submit_rate_compare'])){
	if(($_POST['abpc_bighaat_rate']!='' && $_POST['abpc_bighaat_rate']!=0) || ($_POST['abpc_amazone_rate']!='' && $_POST['abpc_amazone_rate']!=0)){
		if($_POST['abpc_bighaat_rate']==''){
			$bighat_rate = 0;
		}else{
			$bighat_rate = $_POST['abpc_bighaat_rate'];
		}
		if($_POST['abpc_amazone_rate']==''){
			$amazone_rate = 0;
		}else{
			$amazone_rate = $_POST['abpc_amazone_rate'];
		}
		$ins = mysqlQuery("INSERT INTO ab_product_competitor_rate SET abpc_product_id ='".$_POST['product_id']."',
																			abpc_date='".$_POST['abpc_date']."',
																			abpc_variation_id = '".$_POST['abpc_variation_id']."',
																			abpc_bighaat_rate = '".$bighat_rate."',
																			abpc_amazone_rate = '".$amazone_rate."',
																			abpc_created_by = '".$_SESSION['admin_id']."' ");
		header('location:'.$_SERVER['REQUEST_URI']);exit;
	}
}
if(isset($_POST['type']) && $_POST['type']=='add_state_compaign'){
	$month_id = implode(',',$_POST['month']);
	$product_id = $_POST['product_id'];
	foreach ($_POST['state'] as $key => $value) {
		$sel = mysqlQuery("SELECT id FROM ab_product_state_campaign WHERE state_id = '".$value."' AND product_id = '".$product_id."' ");
		if(mysqlNumRow($sel) > 0){
			$upd = "UPDATE ab_product_state_campaign SET month_id = '".$month_id."' WHERE state_id = '".$value."' AND product_id = '".$product_id."' ";
			mysqlQuery($upd);
		}else{
			$ins = "INSERT INTO ab_product_state_campaign SET product_id = '".$product_id."',
																	state_id = '".$value."',
																	month_id = '".$month_id."',
																	status = 'Y' ";
			mysqlQuery($ins);
		}
	}
	$html='';
	$sel_state = mysqlQuery("SELECT c.*,s.abs_name FROM ab_product_state_campaign c JOIN ab_states s ON c.state_id = s.abs_id WHERE product_id = '".$product_id."' ORDER BY id ");
	while($sel_state_res = mysqlFetchArray($sel_state)){
		if($sel_state_res['status']=='Y'){
			$checked = 'checked';
		}else{
			$checked = '';
		}
		$html.='<tr id="statecompaign_'.trim($sel_state_res['id']).'">
			<td><input type="checkbox" name="state_compaign_check" class="state_compaign_check" data-id ="'.$sel_state_res['id'].'" value="Y" '.$checked.' ></td>
			<td>'.$sel_state_res['abs_name'].'</td>
			<td>'.$sel_state_res['month_id'].'</td>
			<td><a href="javascript:void(0);" onClick="ConfirmDelete(\''.trim($sel_state_res['id']).'\')"><img title="Delete" alt="Delete" src="images/Delete - 16.png"></a></td>
		</tr>';
	}
	echo json_encode(array('error'=>0,'html'=>$html)); exit;
}
if(isset($_POST) && $_POST['type']=='update_state_status'){
	$upd = "UPDATE ab_product_state_campaign SET status = '".$_POST['status']."' WHERE id = '".$_POST['id']."'";
		mysqlQuery($upd);
}
if(isset($_POST) && $_POST['type']=='get_seller_price'){
	$sel = mysqlQuery("SELECT price,discount_rs,sale_price,mrp_discount,qty FROM ab_multiseller_product WHERE seller_id = '".$_POST['seller_id']."' AND product_id = '".$_POST['product_id']."' AND variation_id = '".$_POST['variation_id']."' ");
	$sel_res = mysqlFetchArray($sel);
	echo json_encode(array('price'=>$sel_res['price'],'discount_rs'=>$sel_res['discount_rs'],'sale_price'=>$sel_res['sale_price'],'mrp_discount'=>$sel_res['mrp_discount'],'qty'=>$sel_res['qty'])); exit;
}
if(isset($_POST['type']) && $_POST['type']=='change_img_order_no'){
	if($_POST['order_no']!='' && $_POST['product_img_id']!='' && $_POST['order_table_name']!=''){
		if($_POST['order_table_name']=='ab_product_image'){
			$upd = "UPDATE ab_product_image SET abpi_order_no = '".$_POST['order_no']."' WHERE abpi_id = '".$_POST['product_img_id']."' ";
			$upd_qry = mysqlQuery($upd);
			$sel_img_orderno = mysqlQuery("SELECT abpi_product_id,abpi_order_no FROM ab_product_image WHERE abpi_id = '".$_POST['product_img_id']."'");
			$sel_img_orderno_res = mysqlFetchArray($sel_img_orderno);
			$order_no = $sel_img_orderno_res['abpi_order_no'];
		}if($_POST['order_table_name']=='ab_product_image_temp'){
			$upd = "UPDATE ab_product_image_temp SET abpi_order_no = '".$_POST['order_no']."' WHERE abpi_id = '".$_POST['product_img_id']."' ";
			$upd_qry = mysqlQuery($upd);
			$sel_img_orderno = mysqlQuery("SELECT abpi_product_id,abpi_order_no FROM ab_product_image_temp WHERE abpi_id = '".$_POST['product_img_id']."'");
			$sel_img_orderno_res = mysqlFetchArray($sel_img_orderno);
			$order_no = $sel_img_orderno_res['abpi_order_no'];
		}
		$sel_img_no = mysqlQuery("SELECT abpi_product_id,abpi_order_no FROM ab_product_image WHERE abpi_id = '".$_POST['product_img_id']."'");
		$sel_img_no_res = mysqlFetchArray($sel_img_no);
		$select_parimary = "SELECT abpi_image FROM ab_product_image WHERE abpi_product_id = '".$sel_img_no_res['abpi_product_id']."' ORDER BY abpi_order_no ASC LIMIT 1";
		$sel_qry = mysqlFetchAssoc(mysqlQuery($select_parimary));
		$upd = mysqlQuery("UPDATE ab_product SET abpd_image = '".$sel_qry['abpi_image']."' WHERE abpd_id = '".$sel_img_no_res['abpi_product_id']."' ");
		echo json_encode(array('error'=>0,'order_no'=>$order_no)); exit;
	}
}
if(isset($_POST['type']) && $_POST['type']=='get_prod_variation'){
	$html = '';
	$sel = mysqlQuery("SELECT abpdv_id,abpdv_unit_quantity,abpdv_unit,abpdv_notes FROM ab_product_variation WHERE abpdv_status = 'Y' AND abpdv_abpd_id = '".$_POST['prod_id']."' ");
	$html.='<option value="">Select variation</option>';
	if(mysqlNumRow($sel)){
		while($sel_res = mysqlFetchArray($sel)){
			if($sel_res['abpdv_notes']!=''){
				$notes = ' ('.$sel_res['abpdv_notes'].')';
			}else{
				$notes = '';
			}
			$html.='<option value="'.$sel_res['abpdv_id'].'">'.$sel_res['abpdv_unit_quantity'].' '.$sel_res['abpdv_unit'].$notes.'</option>';
		}
	}
	echo json_encode(array('error'=>0,'html'=>$html)); exit;
}
if(isset($_POST['type']) && $_POST['type']=='add_freq_bought'){
	//echo "<pre>"; print_r($_POST); exit;
	$product_id = $_POST['product_id'];
	$variation_id = $_POST['variation_id'];
	$sel_freq = "SELECT abf_id FROM ab_frequency_bought_product WHERE abf_product_id = '".$product_id."' AND abf_variation_id = '".$variation_id."' AND abf_status = 1 ";
	$sel_freq_qry = mysqlQuery($sel_freq); 
	if(mysqlNumRow($sel_freq_qry)>0){
		$sel_freq_res = mysqlFetchArray($sel_freq_qry);
		$last_insert_id = $sel_freq_res['abf_id'];
		/*$upd = "UPDATE ab_frequency_bought_product SET abf_status = 0 WHERE abf_id = '".$sel_freq_res['abf_id']."'";
		$upd_qry = mysqlQuery($upd);
		$upd_detail = "UPDATE ab_frequency_bought_product_detail SET abf_status = 0 WHERE abf_freq_id = '".$sel_freq_res['abf_id']."' ";
		$upd_detail_qry = mysqlQuery($upd_detail);*/
	}else{
		$ins = "INSERT INTO ab_frequency_bought_product SET abf_product_id = '".$product_id."',abf_variation_id = '".$variation_id."' ";
		$ins_qry = mysqlQuery($ins);
		$last_insert_id = mysqlLastId();
	}
	foreach ($_POST['freq_bought_product_id'] as $key => $value) {
		$ins_detail = "INSERT INTO ab_frequency_bought_product_detail SET abf_freq_id = '".$last_insert_id."',abf_main_product_id = '".$product_id."', abf_main_variation_id = '".$variation_id."', abf_product_id = '".$_POST['freq_bought_product_id'][$key]."',abf_variation_id = '".$_POST['freq_bought_variation_id'][$key]."',abf_discount = '".$_POST['discount_val'][$key]."' ";
		$ins_detail_qry = mysqlQuery($ins_detail);	
	}
	$sel_frq = "SELECT fpd.abf_id,p.abpd_name,CONCAT(pv.abpdv_unit_quantity, ' ',pv.abpdv_unit) as main_variation,CONCAT(pvd.abpdv_unit_quantity, ' ',pvd.abpdv_unit) as freq_variation,fpd.abf_discount
	FROM ab_frequency_bought_product_detail fpd
	JOIN ab_product_variation pv ON fpd.abf_main_variation_id = pv.abpdv_id 
	JOIN ab_product p ON fpd.abf_product_id = p.abpd_id 
	JOIN ab_product_variation pvd ON fpd.abf_variation_id = pvd.abpdv_id
	WHERE abf_main_product_id = '".$product_id."' AND abf_main_variation_id = '".$variation_id."' AND abf_status = 1 ";
	$sel_frq_qry = mysqlQuery($sel_frq); 
	$html = '';
	while($sel_frq_res = mysqlFetchAssoc($sel_frq_qry)){
		$html.='<tr>
		<td>'.$sel_frq_res['main_variation'].'</td>
		<td>'.$sel_frq_res['abpd_name'].'</td>
		<td>'.$sel_frq_res['freq_variation'].'</td>
		<td>'.$sel_frq_res['abf_discount'].'</td>
		<td><input type="button" id="del_var_freq_bought" class="btn btn-primary" onclick="remove_dis_product('.$sel_frq_res['abf_id'].')" value="x"></td>
		</tr>';
	}
	echo json_encode(array('error'=>0,'msg'=>'Freq detail added successfully','html'=>$html)); exit;
}
if(isset($_POST['type']) && $_POST['type']=='get_freq_bought'){
	$product_id = $_POST['product_id'];
	$variation_id = $_POST['variation_id'];
	$html = '';
	if($product_id!='' && $variation_id!=''){
		$sel_frq = "SELECT fpd.abf_id,p.abpd_name,CONCAT(pv.abpdv_unit_quantity, ' ',pv.abpdv_unit) as main_variation,CONCAT(pvd.abpdv_unit_quantity, ' ',pvd.abpdv_unit) as freq_variation,fpd.abf_discount
		FROM ab_frequency_bought_product_detail fpd
		JOIN ab_product_variation pv ON fpd.abf_main_variation_id = pv.abpdv_id 
		JOIN ab_product p ON fpd.abf_product_id = p.abpd_id 
		JOIN ab_product_variation pvd ON fpd.abf_variation_id = pvd.abpdv_id
		WHERE abf_main_product_id = '".$product_id."' AND abf_main_variation_id = '".$variation_id."' AND abf_status = 1 ";
		$sel_frq_qry = mysqlQuery($sel_frq); 
		$html = '';
		if(mysqlNumRow($sel_frq_qry)==0){
			$html = '<tr><td colspan="4">No Product Found</td></tr>';
		}
		while($sel_frq_res = mysqlFetchAssoc($sel_frq_qry)){
			$html.='<tr>
			<td>'.$sel_frq_res['main_variation'].'</td>
			<td>'.$sel_frq_res['abpd_name'].'</td>
			<td>'.$sel_frq_res['freq_variation'].'</td>
			<td>'.$sel_frq_res['abf_discount'].'</td>
			<td><input type="button" id="del_var_freq_bought" class="btn btn-primary" onclick="remove_dis_product('.$sel_frq_res['abf_id'].')" value="x"></td>
			</tr>';
		}
	}
	echo json_encode(array('error'=>0,'html'=>$html)); exit;
}
if(isset($_POST['type']) && $_POST['type']=='upd_freq_bought'){
	$product_tbl_id = $_POST['freq_tbl_id'];
	$html = '';
	if($product_tbl_id!=''){
		$upd = mysqlQuery("UPDATE ab_frequency_bought_product_detail SET abf_status = 0 WHERE abf_id = '".$product_tbl_id."' ");
		$sel = mysqlFetchArray(mysqlQuery("SELECT abf_main_product_id,abf_main_variation_id FROM ab_frequency_bought_product_detail WHERE abf_id = '".$product_tbl_id."' "));
		$sel_chk_prod = mysqlQuery("SELECT abf_id FROM ab_frequency_bought_product_detail WHERE abf_status = 1 AND abf_main_product_id = '".$sel['abf_main_product_id']."' AND abf_main_variation_id = '".$sel['abf_main_variation_id']."' ");
		if(mysqlNumRow($sel_chk_prod)==0){
			$upd = mysqlQuery("UPDATE ab_frequency_bought_product SET abf_status = 0 WHERE abf_product_id = '".$sel['abf_main_product_id']."' AND abf_variation_id = '".$sel['abf_main_variation_id']."' ");
		}
		$sel_frq = "SELECT fpd.abf_id,p.abpd_name,CONCAT(pv.abpdv_unit_quantity, ' ',pv.abpdv_unit) as main_variation,CONCAT(pvd.abpdv_unit_quantity, ' ',pvd.abpdv_unit) as freq_variation,fpd.abf_discount
		FROM ab_frequency_bought_product_detail fpd
		JOIN ab_product_variation pv ON fpd.abf_main_variation_id = pv.abpdv_id 
		JOIN ab_product p ON fpd.abf_product_id = p.abpd_id 
		JOIN ab_product_variation pvd ON fpd.abf_variation_id = pvd.abpdv_id
		WHERE abf_main_product_id = '".$sel['abf_main_product_id']."' AND abf_main_variation_id = '".$sel['abf_main_variation_id']."' AND abf_status = 1 ";
		$sel_frq_qry = mysqlQuery($sel_frq); 
		$html = '';
		if(mysqlNumRow($sel_frq_qry)==0){
			$html = '<tr><td colspan="4">No Product Found</td></tr>';
		}
		while($sel_frq_res = mysqlFetchAssoc($sel_frq_qry)){
			$html.='<tr>
			<td>'.$sel_frq_res['main_variation'].'</td>
			<td>'.$sel_frq_res['abpd_name'].'</td>
			<td>'.$sel_frq_res['freq_variation'].'</td>
			<td>'.$sel_frq_res['abf_discount'].'</td>
			<td><input type="button" id="del_var_freq_bought" class="btn btn-primary" onclick="remove_dis_product('.$sel_frq_res['abf_id'].')" value="x"></td>
			</tr>';
		}
	}
	echo json_encode(array('error'=>0,'html'=>$html)); exit;
}
if(isset($_POST['hidden_submit_restricted_state'])){
	if(($_POST['product_id']!='' && $_POST['product_id']!=0) || (!empty($_POST['abrs_state_id'])))
	{
		foreach ($_POST['abrs_state_id'] as $key => $value) {
			$chk_state = "SELECT abrs_id FROM ab_product_restricted_state WHERE abrs_state_id='".$value."' AND abrs_product_id ='".$_POST['product_id']."' ";
			$chk_state_qry = mysqlQuery($chk_state);
			if(mysqlNumRow($chk_state_qry)==0){
				$sel_state_nm = mysqlQuery("SELECT abs_name FROM ab_states WHERE abs_id = '".$value."' ");
				$sel_state_res = mysqlFetchArray($sel_state_nm);
				$ins = mysqlQuery("INSERT INTO ab_product_restricted_state SET abrs_product_id ='".$_POST['product_id']."',
																			abrs_state_id='".$value."',
																			abrs_state_name = '".trim($sel_state_res['abs_name'])."',
																			abrs_created_by = '".$_SESSION['admin_id']."' ");
			}
		}
		header('location:'.$_SERVER['REQUEST_URI']);exit;
	}
}
if(isset($_POST['type']) && $_POST['type']=='del_rest_state'){
	$product_tbl_id = $_POST['rest_state_tbl_id'];
	$product_id = $_POST['product_id'];
	$html = '';
	if($product_tbl_id!=''){
		$del = mysqlQuery("DELETE FROM ab_product_restricted_state WHERE abrs_id = '".$product_tbl_id."' ");
		$sel_rest_state = "SELECT a.abrs_id,s.abs_name
		FROM ab_product_restricted_state a
		JOIN ab_states s ON a.abrs_state_id = s.abs_id 
		WHERE a.abrs_product_id = '".$product_id."' ";
		$sel_rest_state_qry = mysqlQuery($sel_rest_state); 
		$html = '';
		if(mysqlNumRow($sel_rest_state_qry)==0){
			$html = '<tr><td colspan="2">No State Found</td></tr>';
		}
		while($sel_rest_state_res = mysqlFetchAssoc($sel_rest_state_qry)){
			$html.='<tr>
				<td>'.$sel_rest_state_res['abs_name'].'</td>
				<td><input type="button" id="del_restricted_state" class="btn btn-primary" onclick="remove_rest_state('.$sel_rest_state_res['abrs_id'].')" value="x"></td>
			</tr>';
		}
	}
	echo json_encode(array('error'=>0,'html'=>$html)); exit;
}
if(isset($_POST['type']) && $_POST['type']=='add_statewise_product_discount'){
	//echo "<pre>"; print_r($_POST); exit;
	if(($_POST['product_id']!='' && $_POST['product_id']!=0) && $_POST['variation_id']!='' && $_POST['variation_id']!=0 && !empty($_POST['state']) && $_POST['discount']!='' && $_POST['discount']!=0)
	{
		foreach ($_POST['state'] as $sk => $sv) {
			$check_dis = mysqlQuery("SELECT absd_id FROM ab_product_statewise_discount WHERE absd_product_id = '".$_POST['product_id']."' AND absd_variation_id = '".$_POST['variation_id']."' AND absd_state_id='".$sv."' ");
			if(mysqlNumRow($check_dis) > 0){
				$upd = "UPDATE ab_product_statewise_discount SET absd_discount='".$_POST['discount']."' WHERE absd_product_id = '".$_POST['product_id']."' AND absd_variation_id = '".$_POST['variation_id']."' AND absd_state_id='".$sv."' ";
				$upd_qry = mysqlQuery($upd);
				fastrr_product_update_new($_POST['product_id']);
			}else{
				$ins = "INSERT INTO ab_product_statewise_discount SET absd_product_id = '".$_POST['product_id']."',absd_variation_id = '".$_POST['variation_id']."',absd_state_id='".$sv."',absd_discount='".$_POST['discount']."',absd_created_by='".$_SESSION['admin_id']."' ";
				$ins_qry = mysqlQuery($ins);
				fastrr_product_update_new($_POST['product_id']);
			}
		}

		$sel_state_discount = "SELECT a.absd_id,s.abs_name,a.absd_discount,v.abpdv_unit,v.abpdv_unit_quantity,v.abpdv_notes FROM ab_product_statewise_discount a JOIN ab_states s ON a.absd_state_id = s.abs_id JOIN ab_product_variation as v ON v.abpdv_id = a.absd_variation_id WHERE a.absd_product_id = '".$_POST['product_id']."' ORDER BY a.absd_created_at DESC";
		$sel_state_discount_qry = mysqlQuery($sel_state_discount); 
		$html = '';
		if(mysqlNumRow($sel_state_discount_qry)==0){
			$html = '<tr><td colspan="2">No State Found</td></tr>';
		}
		while($sel_state_discount_res = mysqlFetchAssoc($sel_state_discount_qry)){
			if($sel_state_discount_res['abpdv_notes']!=''){
  				$notes = ' ('.$sel_state_discount_res['abpdv_notes'].')';
  			}else{
  				$notes = '';
  			}
			$html.='<tr>
				<td>'.$sel_state_discount_res['abpdv_unit_quantity'].' '.$sel_state_discount_res['abpdv_unit'].$notes.'</td>
				<td>'.$sel_state_discount_res['abs_name'].'</td>
				<td>'.$sel_state_discount_res['absd_discount'].'</td>
				<td><input type="button" id="del_statewise_discount" class="btn btn-primary" onclick="remove_statewise_discount('.$sel_state_discount_res['absd_id'].','.$_POST['product_id'].')" value="x"></td>
			</tr>';
		}
	}
	echo json_encode(array('error'=>0,'html'=>$html)); exit;
}
if(isset($_POST['type']) && $_POST['type']=='del_state_discount'){
	$product_tbl_id = $_POST['state_dis_tbl_id'];
	$product_id = $_POST['product_id'];
	$html = '';
	if($product_tbl_id!=''){
		$del = mysqlQuery("DELETE FROM ab_product_statewise_discount WHERE absd_id = '".$product_tbl_id."' ");
		$sel_state_discount = "SELECT a.absd_id,s.abs_name,a.absd_discount,v.abpdv_unit,v.abpdv_unit_quantity,v.abpdv_notes FROM ab_product_statewise_discount a JOIN ab_states s ON a.absd_state_id = s.abs_id JOIN ab_product_variation as v ON v.abpdv_id = a.absd_variation_id WHERE a.absd_product_id = '".$product_id."' ORDER BY a.absd_created_at DESC";
		$sel_state_discount_qry = mysqlQuery($sel_state_discount); 
		fastrr_product_update_new($product_id);
		$html = '';
		if(mysqlNumRow($sel_state_discount_qry)==0){
			$html = '<tr><td colspan="2">No State Found</td></tr>';
		}
		while($sel_state_discount_res = mysqlFetchAssoc($sel_state_discount_qry)){
			if($sel_state_discount_res['abpdv_notes']!=''){
  				$notes = ' ('.$sel_state_discount_res['abpdv_notes'].')';
  			}else{
  				$notes = '';
  			}
			$html.='<tr>
				<td>'.$sel_state_discount_res['abpdv_unit_quantity'].' '.$sel_state_discount_res['abpdv_unit'].$notes.'</td>
				<td>'.$sel_state_discount_res['abs_name'].'</td>
				<td>'.$sel_state_discount_res['absd_discount'].'</td>
				<td><input type="button" id="del_statewise_discount" class="btn btn-primary" onclick="remove_statewise_discount('.$sel_state_discount_res['absd_id'].','.$product_id.')" value="x"></td>
			</tr>';
		}
	}
	echo json_encode(array('error'=>0,'html'=>$html)); exit;
}
include("header.php");
?>
<link rel="stylesheet" href="css/bootstrap-multiselect.css">
<link rel="stylesheet" href="css/custom-multiselect.css">
<script type="text/javascript" src="js/jquery.form.js"></script>
<script type="text/javascript" src="ckeditors/ckeditor.js"></script>
<script type="text/javascript" src="js/jquery.tagsinput.min.js"></script>
<script src="js/bootstrap-multiselect.js"></script>
<style>
	.extra-large-input { width: 60%; }
	.remains-character{display: block; text-align: left; margin-left: 220px; color: #bb2f0e;}
	.product-message{display: block; text-align: left; margin-left: 220px; color: #bb2f0e;}
	#variationTable label{ text-align: left; }
	#variationTable .single_variation_group_wrap{ border: 2px solid #bb2f0e; padding: 0; width: 100%; }
	#variationTable .single_variation_group_wrap tr td{ background-color: #fff; }
	#variationTable .single_variation_group_wrap > thead > tr > td{ background: #bb2f0e; color: #fff; }
	#variationTable .single_variation_group_wrap > thead > tr { cursor: pointer; }
	#variationTable .single_variation_group_wrap > thead > tr > td .collaspe{ font-size: 35px; float: right; font-weight: bold; cursor: pointer;}
	#variationTable.table-striped > tbody > tr > td{ background-color:#f9f9f9; padding: 0 0 5px 0 !important; }
	.add_variation{ margin-right: 10px; width:120px; padding: inherit;}
	.remove_variation{ width: 120px; padding: inherit;}
	.product_dimension p input{ width: 90%;}
	.product_dimension p select{ width: 96%; }
	.product_dimension p { width: 30%; float: left; box-sizing: border-box; margin-right: 10px !important; }
	.error{display: block;text-align: left;margin-left: 220px;color: #bb2f0e;}
	.recomadend_wrap #similar_prod_table{padding: 40px 0 0 0;}
    .recomadend_wrap #same_prod_table{padding: 40px 0 0 0;}
	.mainProd{color: #0075ff;}
	.btn-group>.btn:first-child{ width: 224px; }
	.multiselect-container>li>a>label{ text-align:left;}
	.ui-autocomplete {
		position:absolute;
    	cursor:pointer;
    	z-index:1001 !important;
		list-style:none;
		background-color:#ECECEC;
		color: #000;
		padding-left: 10px;
		max-height: 350px;
    	overflow: hidden;
	}
</style>
<script>
	jQuery(document).ready(function(){
		var msg = '<?php echo $_REQUEST['msg']; ?>';
		if(msg!=''){
			if(msg=='similar_not_add'){
				jAlert('You can not add similar products to this product.!','Alert Dialog',function(){
					window.history.pushState('', '', removeURLParameter(window.location.href, 'msg'));
				});
			}
			if(msg=='product_inactive'){
				jAlert('Only active status add similar product. Please change product status.','Alert Dialog',function(){
					window.history.pushState('', '', removeURLParameter(window.location.href, 'msg'));
				});
			}
			if(msg=='product_waiting_approval'){
				jAlert('Only active status add similar product. Please change product status.!','Alert Dialog',function(){
					window.history.pushState('', '', removeURLParameter(window.location.href, 'msg'));
				});
			}
			if(msg=='status_change'){
				jAlert('Please change product status Because this product in list of similar product.!','Alert Dialog',function(){
					window.history.pushState('', '', removeURLParameter(window.location.href, 'msg'));
				});
			}
		}
		jQuery("#abpd_technical_id").change(function(){
			var option = jQuery('option:selected', this).attr('data-tags');
			var sel_cat = jQuery("#abpd_technical_id").find(':selected').attr('data-abt_cat_id');	
			var sel_sub_cat = jQuery("#abpd_technical_id").find(':selected').attr('data-abt_subcat_id');	
			var sel_child_cat = jQuery("#abpd_technical_id").find(':selected').attr('data-abt_childcat_id');

			getsubcat(sel_cat,sel_sub_cat);
			getsubsubcat(sel_sub_cat,sel_child_cat);

			jQuery("#abpd_cat_id").val(sel_cat);
			jQuery("#ab_subcat_id").val(sel_sub_cat);
			jQuery("#ab_childcat_id").val(sel_child_cat);
			//console.log("O : " + option);
			var product_tags = jQuery('#abpd_product_tags_tagsinput .tag').length;
			if(product_tags==0){
				jQuery('#abpd_product_tags').importTags('');
				var array = option.split(",");
				for (i=0;i<array.length;i++){
					jQuery('#abpd_product_tags').addTag(array[i]);
				}
			}
			//SHIPPED BY
			var technical_shipped_by = jQuery("#abpd_technical_id").find(':selected').attr('data-technical_shipped_by');
         var technical_non_shipped_by = jQuery("#abpd_technical_id").find(':selected').attr('data-technical_non_shipped_by');
         var comp_id = jQuery("#abpd_comp_id").find(':selected').attr('data-is_branded');
         var technical_id = jQuery('#abpd_technical_id').find(":selected").val();
         var variationdiv = jQuery('.single_variation_group_wrap');
         if(jQuery.type(technical_shipped_by) === "undefined"){
                technical_shipped_by = '';
            }
            if(jQuery.type(technical_non_shipped_by) === "undefined"){
                technical_non_shipped_by = '';
            }
            if(jQuery.type(comp_id) === "undefined"){
                comp_id = '';
            }
            var allow_disabled = 0;
            jQuery(".shipped_by option").each(function(i){
                var shipped_by_val = this.value; //jQuery(this).val();
                if(comp_id=='Y'){
                    //if(shipped_by_val == 'Agribegri'){
                    if(shipped_by_val == technical_shipped_by){
                        allow_disabled = 1;
                    }
                }
                if(technical_shipped_by!='' || technical_non_shipped_by!=''){
                    if(shipped_by_val == technical_shipped_by || shipped_by_val == technical_non_shipped_by){
                        allow_disabled = 1;
                    }
                }
            });
            console.log(allow_disabled);
            if(allow_disabled==1){
                //var check_weight_packing = '';
                if(comp_id=='Y'){
                    //jQuery(variationdiv).find(".shipped_by").val('Agribegri');
                    jQuery(variationdiv).find(".shipped_by").val(technical_shipped_by);
                    jQuery(variationdiv).find('.shipped_by option:not(:selected)').attr('disabled', true);
                    jQuery(variationdiv).find('.shipped_by option:selected').attr('disabled', false);
                    jQuery(variationdiv).find(".abpdv_payment_method").val('online');
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').hide();
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').hide();
                    var shipped_by = jQuery(variationdiv).find('.shipped_by').val();
                    var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
                    jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
                        if(shipped_by == shippedBy){
                            shipping_commission = commission_per;
                        }
                    });
                    jQuery('.abpd_weight_packing').each(function(){   
                        var check_weight_packing = jQuery(this).val();
                        if(check_weight_packing <=5000 && shipping_commission <=15){
                            jQuery(variationdiv).find('.abpdv_delhivery').val('Y');
                            calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                        }
                    });
                }else if(technical_non_shipped_by!='' && (comp_id=='N' || comp_id=='')){
                    jQuery(variationdiv).find('.shipped_by').val(technical_non_shipped_by);
                    jQuery(variationdiv).find('.shipped_by option:not(:selected)').attr('disabled', true);
                    jQuery(variationdiv).find('.shipped_by option:selected').attr('disabled', false);
                    var shipped_by = jQuery(variationdiv).find('.shipped_by').val();
                    //console.log(shipped_by);
                    var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
                    jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
                        if(shipped_by == shippedBy){
                            shipping_commission = commission_per;
                        }
                    });
                    if(shipping_commission<=15){
                        jQuery(variationdiv).find(".abpdv_payment_method").val('online');
                        jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').hide();
                        jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').hide();
                        jQuery('.abpd_weight_packing').each(function(){   
                            var check_weight_packing = jQuery(this).val();
                            if(check_weight_packing <=5000){
                                jQuery(variationdiv).find('.abpdv_delhivery').val('Y');
                                calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                            }
                        });
                    }else{
                        jQuery(variationdiv).find(".abpdv_payment_method").val('');
                        jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').show();
                        jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').show();
                        jQuery('.abpd_weight_packing').each(function(){ 
                            jQuery(variationdiv).find('.abpdv_delhivery').val('');
                            calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                        });
                    }
                }else if(technical_non_shipped_by=='' && (comp_id=='N' || comp_id=='')){
                    jQuery(variationdiv).find(".shipped_by").val('');
                    jQuery(variationdiv).find('.shipped_by option').attr('disabled', false);
                    jQuery(variationdiv).find(".abpdv_payment_method").val('');
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').show();
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').show();
                    jQuery('.abpd_weight_packing').each(function(){ 
                        jQuery(variationdiv).find('.abpdv_delhivery').val('');
                        calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                    });
                }
            }else{
                jQuery(variationdiv).find(".shipped_by").val('');
                jQuery(variationdiv).find('.shipped_by option').attr('disabled', false);
                jQuery(variationdiv).find(".abpdv_payment_method").val('');
                jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').show();
                jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').show();
                jQuery('.abpd_weight_packing').each(function(){ 
                    jQuery(variationdiv).find('.abpdv_delhivery').val('');
                    calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                });
            } 		
		});
	});
	function checkNewComp(val) {
        //console.log(val);
        var technical_shipped_by = jQuery("#abpd_technical_id").find(':selected').attr('data-technical_shipped_by');
        var technical_non_shipped_by = jQuery("#abpd_technical_id").find(':selected').attr('data-technical_non_shipped_by');
        var comp_id = jQuery("#abpd_comp_id").find(':selected').attr('data-is_branded');
        //var variationdiv = jQuery('table .add_variable');
        var variationdiv = jQuery('.single_variation_group_wrap');
        jQuery('.shipped_by option:not(:selected)').attr('disabled', false);    
        if(jQuery.type(technical_shipped_by) === "undefined"){
            technical_shipped_by = '';
        }
        if(jQuery.type(comp_id) === "undefined"){
            comp_id = '';
        }
        var allow_disabled = 0;
        jQuery(".shipped_by option").each(function(i){
            //console.log(this.value);
            var shipped_by_val = this.value;
            if(comp_id=='Y'){
                if(shipped_by_val == technical_shipped_by){
                    allow_disabled = 1;
                }
            }
            if(technical_shipped_by!=''){
                if(shipped_by_val == technical_shipped_by || shipped_by_val == technical_non_shipped_by){
                    allow_disabled = 1;
                }
            }
        });
        if(allow_disabled==1){
            if(comp_id=='Y'){
                //jQuery(variationdiv).find(".shipped_by").val('Agribegri');
                jQuery(variationdiv).find(".shipped_by").val(technical_shipped_by);
                jQuery(variationdiv).find('.shipped_by option:not(:selected)').attr('disabled', true);
                jQuery(variationdiv).find('.shipped_by option:selected').attr('disabled', false);
                jQuery(variationdiv).find(".abpdv_payment_method").val('online');
                jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').hide();
                jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').hide();
                var shipped_by = jQuery(variationdiv).find('.shipped_by').val();
                var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
                jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
                    if(shipped_by == shippedBy){
                        shipping_commission = commission_per;
                    }
                });
                jQuery('.abpd_weight_packing').each(function(){   
                    var check_weight_packing = jQuery(this).val();
                    if(check_weight_packing <=5000 && shipping_commission <=15){
                        jQuery(variationdiv).find('.abpdv_delhivery').val('Y');
                        calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                    }
                });
            }else if(technical_non_shipped_by!='' && (comp_id=='N' || comp_id=='')){
                jQuery(variationdiv).find('.shipped_by').val(technical_non_shipped_by);
                jQuery(variationdiv).find('.shipped_by option:not(:selected)').attr('disabled', true);
                jQuery(variationdiv).find('.shipped_by option:selected').attr('disabled', false);
                var shipped_by = jQuery(variationdiv).find('.shipped_by').val();
                var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
                jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
                    if(shipped_by == shippedBy){
                        shipping_commission = commission_per;
                    }
                });
                console.log(shipped_by);
                if(shipping_commission<=15){
                    jQuery(variationdiv).find(".abpdv_payment_method").val('online');
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').hide();
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').hide();
                    jQuery('.abpd_weight_packing').each(function(){   
                        var check_weight_packing = jQuery(this).val();
                        if(check_weight_packing <=5000){
                            jQuery(variationdiv).find('.abpdv_delhivery').val('Y');
                            calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                        }
                    });
                }else{
                    jQuery(variationdiv).find(".abpdv_payment_method").val('');
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').show();
                    jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').show();
                    jQuery('.abpd_weight_packing').each(function(){ 
                        jQuery(variationdiv).find('.abpdv_delhivery').val('');
                        calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                    });
                }
            }else if(technical_non_shipped_by=='' && (comp_id=='N' || comp_id=='')){
                jQuery(variationdiv).find(".shipped_by").val('');
                jQuery(variationdiv).find('.shipped_by option').attr('disabled', false);
                jQuery(variationdiv).find(".abpdv_payment_method").val('');
                jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').show();
                jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').show();
                jQuery('.abpd_weight_packing').each(function(){ 
                    jQuery(variationdiv).find('.abpdv_delhivery').val('');
                    calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
                });
            }
        }else{
            jQuery(variationdiv).find(".shipped_by").val('');
            jQuery(variationdiv).find('.shipped_by option').attr('disabled', false);
            jQuery(variationdiv).find(".abpdv_payment_method").val('');
            jQuery(variationdiv).find('.abpdv_payment_method option[value="cod"]').show();
            jQuery(variationdiv).find('.abpdv_payment_method option[value="both"]').show();
            jQuery('.abpd_weight_packing').each(function(){ 
                jQuery(variationdiv).find('.abpdv_delhivery').val('');
                calculate_courier_charge(jQuery(this).parents('.single_variation_group_wrap'));
            });
        }
    }
</script>
</head>
<body>
	<div id="mainwrapper" class="mainwrapper">
		<div class="header">
			<?php include('Include/header.php'); ?>
		</div>
	  <div class="leftpanel">
	  	<?php include('Include/left_menu.php'); ?>
	  </div>
	  <div  class="rightpanel">
	  	<ul class="breadcrumbs">
	  		<li><a href="dashboard.php"><i class="iconfa-home"></i></a> <span class="separator"></span></li>
	  		<li>Manage Products<span class="separator"></span></li>
	  		<li>Products</li>
	  	</ul>
	    <div class="pageheader">
	    	<div class="searchbar">
	    		<a href="manage_products.php">
		    		<button class="btn btn-primary btn-large">Back</button>
	    		</a>
	    	</div>
	    	<div class="pageicon">
	    		<span class="iconfa-shopping-cart"></span>
	    	</div>
	      <div class="pagetitle">
	      	<h5>Add, Edit Or Delete All of Your Products.</h5>
	      	<h1>Manage Products</h1>
	      </div>
	    </div>
	    <!--pageheader-->
	    <div class="maincontent">
	      <div class="maincontentinner">
	        <div class="row-fluid" >
	          <div class="span8">
	            <div class="widgetbox">
	              <div class="headtitle">
	                <h4 class="widgettitle"><?php echo ($product_data['abpd_id'] > 0) ? 'Edit' : 'Add'; ?> Product</h4>
	              </div> <!-- END OF headtitle -->
	              <div class="widgetcontent" >
	              	<form name="prdct_frm" id="prdct_frm" action="" method="post" class="stdform" enctype="multipart/form-data">
	                  <input type="hidden" name="abpd_id" id="abpd_id" value="<?php echo $product_data['abpd_id'] ?>" >
	                  <input type="hidden" name="rand_idads" id="rand_idads" value="<?php echo $_SESSION['rand_idads']; ?>" >
	                  <input type="hidden" name="pnmae_exist" value="No" id="pnmae_exist" >
	                  <input type="hidden" name="abpd_code" value="<?php echo $product_data['abpd_code']; ?>" id="abpd_code" >
                      <input type="hidden" name="abpd_userid" value="<?php echo $_SESSION['admin_id']; ?>" id="abpd_userid" >
                      <input type="hidden" name="abpd_original_status" value="<?php echo $product_data['abpd_active']; ?>" id="abpd_original_status" >
	                	<p style="color:red;">
	                  	<?php echo $err_msg;?>
	                  </p>
	                  <!-- STATUS -->
                      <?php 
					  		$statusactive = "";
							if($product_data['abpd_active']!="Y" && ($product_data['abpd_approve11']!="Y")){
								$statusactive = "disabled";
					  		}
					?>
	                  <p>
	                    <label>Status<span style="color:#FF0000;">*</span> :</label>
	                    <select name="abpd_active" id="abpd_active" class="select-large">
	                      <option <?php echo ($product_data['abpd_active']=='PND') ? 'selected="selected"' : ''; ?> value="PND">Pending</option>
	                      <option <?php echo ($product_data['abpd_active']=='Y') ? 'selected="selected"' : ''; echo $statusactive;?> value="Y">Active</option>
	                      <option <?php echo ($product_data['abpd_active']=='N') ? 'selected="selected"' : ''; ?> value="N">Waiting for Approval</option>
	                      <option <?php echo ($product_data['abpd_active']=='D') ? 'selected="selected"' : ''; ?> value="D">Inactive</option>
	                      <option <?php echo ($product_data['abpd_active']=='DEL') ? 'selected="selected"' : ''; ?> value="DEL">Deleted</option>
	                    </select>
                        <?php if($abpd_id > 0 ){ ?>
                        	<span style="padding-left:20px;">
                            	<?php if($_SESSION['admin_id']=='1' || $_SESSION['admin_id']=='11'){ 
										if($product_data['abpd_approve11']=="Y"){$selchk11 = "checked=''"; $seldis11="disabled=''"; $selchk11date = " - ".date("d-m-Y h:i:s",strtotime($product_data['abpd_approve11_datetime']));}
								?>
                                <input type="checkbox" id="chk_approve11" value="Y" <?php echo $selchk11; echo $seldis11;?> name="chk_approve11">&nbsp;Approved by Vipul&nbsp;<?php echo $selchk11date; ?> &nbsp;&nbsp;&nbsp;&nbsp;
                                <?php } ?>
                                <?php if($_SESSION['admin_id']=='1' || $_SESSION['admin_id']=='20'){ 
										if($product_data['abpd_approve20']=="Y"){$selchk20 = "checked=''";  $seldis20="disabled=''"; $selchk20date = " - ".date("d-m-Y h:i:s",strtotime($product_data['abpd_approve20_datetime']));}
								?>
                                <input type="checkbox" id="chk_approve20" value="Y" <?php echo $selchk20; echo $seldis20;?> name="chk_approve20">&nbsp;Approved by Bhavik&nbsp;<?php echo $selchk20date; ?>
                                <?php } ?>
                            </span>
                        <?php } ?>
	                  </p>
	                  <!-- PRODUCT APPROVED DATE IF ACTIVE AND APPROVED -->
	                  <?php if($product_data['abpd_active']=='Y' && ($product_data['abpd_approved_date'] != '' && $product_data['abpd_approved_date'] != '0000-00-00') ){ ?>
	                    <p>
	                       <label>Approved Date : </label> 
	                       <input type="text" name="abpd_approved_date" value="<?php echo $product_data['abpd_approved_date']; ?>" readonly />
	                    </p>
					  				<?php } ?>
					  				<!-- COMPANY DROPDOWN -->
	                  <p>
	                		<label>Company / Manufacturer<span style="color:#FF0000;">*</span> :</label>
	                		<select name="abpd_comp_id" id="abpd_comp_id" onChange="checkNewComp(this.value)" style=" <?php echo setBorder($product_data['abpd_comp_id']);?> " class="select-large" >
	                			<option value="">- - - Select Company - - -</option>
	                			<?php 
	                				$getCompaniesQry = "SELECT * FROM ab_company WHERE abc_name<>'' AND abc_active='Y' ORDER BY abc_name ASC";
	                				$getCompaniesQry = $pdo->_prepare($getCompaniesQry);
	                				$companies = $pdo->_resultset();
	                				foreach ($companies as $key => $row_com){
	                					$selected = ($product_data['abpd_comp_id']==$row_com['abc_id']) ? 'selected="selected"' : ''; 
	                					?>
	                					<option value="<?php echo $row_com['abc_id']; ?>" <?php echo $selected; ?> data-is_branded="<?php echo $row_com['abc_branded']; ?>"><?php echo $row_com['abc_name']; ?></option>;
	                       	<?php } ?>
	                    </select>
	                  </p>
	                  <!-- SELLER DROPDOWN -->
					  				<p>
	                    <label>Seller <span style="color:#FF0000;">*</span> :</label>
	                    <input type="hidden" name="abpd_seller_status" id="abpd_seller_status" value=""	>
	                    <select onChange="fill_seller_status();" name="abpd_absupplier_id" id="abpd_absupplier_id" class="select-large">
	                      <option value="">- - - Select Seller - - -</option>
	                      <?php 
													$sellerQry = $pdo->_prepare("SELECT absl_id, absl_name,absl_status,absl_seller_type FROM ab_seller WHERE absl_status <> 'deleted' ORDER BY absl_name ASC");
													$sellerData = $pdo->_resultset();
													foreach ($sellerData as $key => $seller) {
														$selected = ($seller['absl_id'] == $product_data['abpd_absupplier_id']) ? 'selected="selected"' : '';
														?>
														<option absl_status="<?php echo $seller['absl_status']; ?>" absl_seller_type="<?php echo $seller['absl_seller_type']; ?>" value="<?php echo $seller['absl_id']; ?>" <?php echo $selected; ?>><?php echo $seller['absl_name']; ?></option>
													<?php } ?>
	                    </select>
	                  </p>
	                  <p>
	                  	<label>Seller Status<span style="color:#FF0000;">*</span> :</label>
	                    <select name="seller_sale_status"  id="seller_sale_status" tabindex="1" class="select-large" >
	                      <option <?php echo ($product_data['seller_sale_status']=='Y') ? 'selected="selected"' : ''; ?> value="Y">Active</option>
	                      <option <?php echo ($product_data['seller_sale_status']=='N') ? 'selected="selected"' : ''; ?> value="N">Inactive</option>
	                    </select>
	                  </p>
	                  <p>
	                    <label>Category<span style="color:#FF0000;">*</span> :</label>
	                    <select onChange="getsubcat(this.value);"  style=" <?php echo setBorder($product_data['abpd_cat_id']);?> " name="abpd_cat_id" id="abpd_cat_id" class="select-large" >
	                    	<option value="">- - - Select Category - - -</option>
	                    	<?php
	                    		$getCatQry = "SELECT * FROM ab_category WHERE ab_cat_name<>'' AND ab_cat_active='Y' ORDER BY ab_cat_name ASC";
	                				$getCatQry = $pdo->_prepare($getCatQry);
	                				$categories = $pdo->_resultset();
	                    		foreach ($categories as $key => $row_cat) {
	                    			$selected = ($product_data['abpd_cat_id']==$row_cat['ab_cat_id']) ? 'selected="selected"' : '';
	                    			?>
	                    			<option value="<?php echo $row_cat['ab_cat_id']; ?>" <?php echo $selected; ?>><?php echo ucwords(strtolower($row_cat['ab_cat_name'])); ?></option>
	                    		<?php } ?>
	                    	</select>
	                  </p>
	                  <p>
	                  	<label>Sub Category :</label>
	                  	<select name="ab_subcat_id" id="ab_subcat_id" class="select-large" onChange="getsubsubcat(this.value);" >
	                  		<option value="">- - - Select Sub Category - - -</option>
	                  		<?php
	                  			$getSubCatQry = "SELECT * FROM ab_sub_category where ab_cat_active='Y' and ab_maincat_id = '".$product_data['abpd_cat_id']."' ORDER BY ab_subcat_name ASC";
	                				$getSubCatQry = $pdo->_prepare($getSubCatQry);
	                				$subCategories = $pdo->_resultset();
	                				foreach ($subCategories as $key => $sub_cat) {
	                					$selected = ($product_data['abpd_subcat_id']==$sub_cat['ab_cat_id']) ? 'selected="	selected"' : '';
	                					?>
	                					<option value="<?php echo $sub_cat['ab_cat_id']; ?>" <?php echo $selected; ?>><?php echo ucwords(strtolower($sub_cat['ab_subcat_name'])); ?></option>
	                				<?php } ?>
	                    </select>
	                  </p>
	                  <p>
	                  	<label>Sub Sub Category :</label>
	                  	<select name="ab_childcat_id" id="ab_childcat_id" class="select-large" >
	                  		<option value="">- - - Select Child Sub Category - - -</option>
	                  		<?php	
	                  			$getSubSubCatQry = "SELECT * FROM ab_sub_sub_category WHERE ab_cat_active='Y' AND ab_subcat_id = '".$product_data['abpd_subcat_id']."' ORDER BY ab_subcat_name ASC";
	                				$getSubSubCatQry = $pdo->_prepare($getSubSubCatQry);
	                				$subSubCategories = $pdo->_resultset();
	                				foreach ($subSubCategories as $key => $sub_sub_cat) {
	                					$selected = ($product_data['abpd_childcat_id']==$sub_sub_cat['ab_cat_id']) ? 'selected="	selected"' : '';
	                					?>
	                					<option value="<?php echo $sub_sub_cat['ab_cat_id']; ?>" <?php echo $selected; ?>><?php echo ucwords(strtolower($sub_sub_cat['ab_subcat_name'])); ?></option>
	                				<?php } ?>
	                    </select>
	                  </p>
	                  <p>
	                  	<label>Crop Category :</label>
	                  	<select name="ab_cropcat_id[]" id="ab_cropcat_id" class="input-medium" multiple=''>
	                  		<?php	
	                  			$getCropCatQry = "SELECT * FROM ab_crop_category where ab_cat_status='Y' ORDER BY ab_cat_name ASC";
	                				$getCropCatQry = $pdo->_prepare($getCropCatQry);
	                				$CropCategories = $pdo->_resultset();
	                				$exp_product = explode(',',$product_data['abpd_cropcat_id']);
	                				foreach ($CropCategories as $key => $crop_cat) {
	                					if(in_array($crop_cat['ab_cat_id'], $exp_product)){ 
												$selected = 'selected="selected"';
											}else{
												$selected = '';
											}
	                					//$selected = ($product_data['abpd_cropcat_id']==$crop_cat['ab_cat_id']) ? 'selected="	selected"' : '';
	                					?>
	                					<option <?php echo $selected; ?> value="<?php echo $crop_cat['ab_cat_id']; ?>" ><?php echo ucwords(strtolower($crop_cat['ab_cat_name'])); ?></option>
	                				<?php } ?>
	                    	</select>
	                  </p>
                      <p>
	                  	<label>Technical Content :</label>
	                  	<select name="abpd_technical_id" id="abpd_technical_id" class="select-large" >
	                  		<option value="0">- - - Select Technical Content - - -</option>
	                  		<?php	
								$getTechQry = "SELECT * FROM ab_product_technical WHERE abt_status='Y' ORDER BY abt_name ASC";
								$getTechQry = $pdo->_prepare($getTechQry);
								$TechCategories = $pdo->_resultset();
								foreach ($TechCategories as $key => $prod_techcontent) {
									$selected = ($product_data['abpd_technical_id']==$prod_techcontent['abt_id']) ? 'selected="	selected"' : '';
									?>
									<option value="<?php echo $prod_techcontent['abt_id']; ?>" <?php echo $selected; ?> data-technical_shipped_by='<?php echo $prod_techcontent['abt_shipped_by']; ?>' data-technical_non_shipped_by='<?php echo $prod_techcontent['abt_non_branded_shipped_by']; ?>' data-abt_cat_id='<?php echo $prod_techcontent['abt_cat_id']; ?>' data-abt_subcat_id='<?php echo $prod_techcontent['abt_subcat_id']; ?>' data-abt_childcat_id='<?php echo $prod_techcontent['abt_childcat_id']; ?>' data-tags="<?php echo $prod_techcontent['abt_tags']; ?>"><?php echo ucwords(strtolower($prod_techcontent['abt_name'])); ?></option>
								<?php } ?>
	                    </select>
	                  </p>
	                  <p>
	                  	<label>Same Product :</label>
	                  	<select name="abpd_same_product_id" id="abpd_same_product_id" class="select-large" >
	                  		<option value="0">- - - Select Same Product - - -</option>
	                  		<?php	
								$getsameQry = "SELECT * FROM ab_same_product WHERE abs_status='Y' ORDER BY abs_name ASC";
								$getsameQry = $pdo->_prepare($getsameQry);
								$Sameproduct = $pdo->_resultset();
								foreach ($Sameproduct as $key => $prod_same) {
									$selected = ($product_data['abpd_same_product_id']==$prod_same['abs_id']) ? 'selected="	selected"' : '';
									?>
									<option value="<?php echo $prod_same['abs_id']; ?>" <?php echo $selected; ?>><?php echo ucwords(strtolower($prod_same['abs_name'])); ?></option>
								<?php } ?>
	                    </select>
	                  </p>
	                  <p>
	                  	<label>Multi Seller Product :</label>
	                    	<input type="checkbox" name="abpd_multi_seller_product" id="abpd_multi_seller_product" value="Y" class="input-large" <?php echo ($product_data['abpd_multi_seller_product'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                    <label>Name<span style="color:#FF0000;">*</span> :</label>
	                    <input onBlur="Check_name(this.value)" onChange="Check_name(this.value)" type="text" style="width:60%;<?php echo setBorder($product_data['abpd_name']); ?>" name="abpd_name" id="abpd_name" value="<?php echo $product_data['abpd_name']; ?>" class="input-large min_max_char_validation" maxlength="150" minlength='75'>
	                    	<span class="remains-character"><?php echo strlen($product_data['abpd_name']); ?> characters.</span>
	                 		<span class="product-message">(Product name must be of minimum 75 characters and maximum 150 characters.)</span>
	                  </p>
	                  <!-- <p>
	                    <label>Technical Name :</label>
	                    <input type="text" name="abpd_technical_name" id="abpd_technical_name" value="<?php //echo $product_data['abpd_technical_name']; ?>" class="input-large" >
	                  </p> -->
	                  <p>
	                  	<label>User Name :</label>
	                  	<input type="text" name="abpd_user_id" id="abpd_user_id" value="<?php echo $product_data['abpd_user_id']; ?>" class="input-large" >
	                  </p>
	                  <p>
	                    <label>Rate compare :</label>
	                    <textarea name="rate_compare" id="rate_compare" class="input-large" style="width: 270px; height: 100px;"><?=$product_data['rate_compare'];?></textarea>
										</p>
										<p>
											<label>Description <span style="color:#FF0000;">*</span> :</label>
											<div style="padding-left:218px; margin: 5px 0;">
												<?php if($product_data['abpd_descri']==""){ ?>
													<style>
													#cke_abpd_descri { border:1px solid red;}
													</style>
												<?php } ?>
												<textarea name="abpd_descri"  id="abpd_descri" ><?php echo $product_data['abpd_descri']; ?></textarea>
											</div>
										</p>
										<p>
	                    <label>Product Tags :</label>
	                    <input  name="abpd_product_tags" id="abpd_product_tags" class="input-large"  value="<?php echo $product_data['abpd_product_tags']; ?>" >
                        <?php
							if($product_data['abpd_product_tags']!=''){
								$exp_product_tag = explode(',',$product_data['abpd_product_tags']);
								$count_product_tags = count($exp_product_tag);
							}else{
								$count_product_tags = 0;
							}
							if($count_product_tags>0){
								echo "<span class=\"remains-character\">Total Tags:".$count_product_tags."</span>";
							}
						?>
	                  </p>
	                  <p>
	                    <label>GST<span style="color:#FF0000;">*</span> :</label>
                        <select name="abpd_gst_no" id="abpd_gst_no" class="select-large" >
	                  		<option value="">- - - Select GST No - - -</option>
	                  		<option <?php if($product_data['abpd_gst_no']==0){ echo "selected"; } ?> value="0">0</option>
                            <option <?php if($product_data['abpd_gst_no']==5){ echo "selected"; } ?> value="5">5</option>
	                  		<option <?php if($product_data['abpd_gst_no']==12){ echo "selected"; } ?> value="12">12</option>
	                  		<option <?php if($product_data['abpd_gst_no']==18){ echo "selected"; } ?> value="18">18</option>
	                  		<option <?php if($product_data['abpd_gst_no']==28){ echo "selected"; } ?> value="28">28</option>
	                    </select>
	                   <?php /*?> <input type="text" name="abpd_gst_no" id="abpd_gst_no" value="<?php echo $product_data['abpd_gst_no']; ?>" style=" <?php echo setBorder($product_data['abpd_gst_no']);?> " class="input-large" ><?php */?>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                    <label>HSN Code :</label>
	                    <input type="text" name="abpd_hsn_code" id="abpd_hsn_code" value="<?php echo $product_data['abpd_hsn_code']; ?>" class="input-large " style="<?php echo $product_data['abpd_hsn_code']; ?>">
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Is returnable :</label>
	                    <input type="checkbox" name="abpd_returnable" id="abpd_returnable" value="Y" class="input-large" <?php echo ($product_data['abpd_returnable'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
	                  <?php
					  		$strmetadisable = "readonly";
					  		if($_SESSION['admin_id'] == '1'){
								$strmetadisable = "";
							}else if($access_array['aba_product_meta']['abaac_manage']=='Yes'){
								$strmetadisable = "";
							}else if($abpd_id==""){
								$strmetadisable = "";
							}else if($product_data['abpd_new_meta_title']!="" && $_SESSION['admin_id'] == '1'){
								$strmetadisable = "";
							}
							/*else if($product_data['abpd_active']!="Y" && $_SESSION['admin_id'] != '1'){
								$strmetadisable = "";
							}*/
					  ?>
                      <p>
	                    <label>Meta Title<span style="color:#FF0000;">*</span> :</label>
	                    <input type="text" <?php echo $strmetadisable; ?> name="abpd_meta_title" id="abpd_meta_title" value="<?php echo $product_data['abpd_new_meta_title']; ?>" class="input-large extra-large-input max_char_validation" maxlength="120" style="<?php echo setBorder($product_data['abpd_meta_title']); ?>">
	                    <span class="remains-character"><?php echo 120-strlen($product_data['abpd_new_meta_title']); ?> characters remains.</span>
	                  </p>
	                  <?php /* ?><p>
	                    <label>Meta Keywords<span style="color:#FF0000;">*</span> :</label>
	                    <textarea name="abpd_meta_keyword" <?php echo $strmetadisable; ?> id="abpd_meta_keyword" value="" class="input-large extra-large-input max_char_validation"  maxlength="600" style="<?php echo setBorder($product_data['abpd_meta_keyword']); ?>"><?php echo $product_data['abpd_meta_keyword']; ?></textarea>
	                    <span class="remains-character"><?php echo 600-strlen($product_data['abpd_meta_keyword']); ?> characters remains.</span>
	                  </p><?php */ ?>
	                  <p>
	                    <label>Meta Description<span style="color:#FF0000;">*</span> :</label>
	                    <textarea name="abpd_meta_desc" <?php echo $strmetadisable; ?> id="abpd_meta_desc" class="input-large extra-large-input max_char_validation" maxlength="360" style="<?php echo setBorder($product_data['abpd_meta_desc']); ?>"><?php echo $product_data['abpd_meta_desc']; ?></textarea>
	                    <span class="remains-character"><?php echo 360-strlen($product_data['abpd_meta_desc']); ?> characters remains.</span>
	                  </p>
	                  <p>
	                    <label>Upload guideline document :</label>
	                  	<input type="file" name="abpd_guildline_doc" id="abpd_guildline_doc">
	                  	<?php if($product_data['abpd_guildline_doc']!=''){?>
	                  	<a href="<?php echo $SITE_TITLE_URL.'images/guildline_document/'.$product_data['abpd_guildline_doc']; ?>" target="_blank">View</a>&nbsp;&nbsp;&nbsp;
                  		<a href="javascript:void(0);" onClick="deleteGuideline('<?php echo $product_data['abpd_id']?>');">Remove</a>
	                    <?php } ?>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                    <label>Upload Facebook Feed Image :</label>
	                  	<input type="file" name="abpd_facebook_feed_img" id="abpd_facebook_feed_img">
	                  	<input type="hidden" name="hidden_abpd_facebook_feed_img" value="<?php echo $product_data['abpd_facebook_feed_img']; ?>">
	                  	<?php if($product_data['abpd_facebook_feed_img']!=''){?>
	                  	<a href="<?php echo $SITE_TITLE_URL.'admin/images/prod_image/'.$product_data['abpd_facebook_feed_img']; ?>" target="_blank">View</a>&nbsp;&nbsp;&nbsp;
                  		<a href="javascript:void(0);" onClick="deleteFacebookImg('<?php echo $product_data['abpd_id']?>');">Remove</a>
	                    <?php } ?>
	                  </p>
	                  <p>&nbsp;</p>
					<p>
	                	<label>Upload A+ Image :</label>
	                  	<input type="file" name="abpd_aplus_img" id="abpd_aplus_img">
	                  	<input type="hidden" name="hidden_abpd_aplus_img" value="<?php echo $product_data['abpd_aplus_img']; ?>">
	                  	<?php if($product_data['abpd_aplus_img']!=''){?>
	                  		<a href="<?php echo $SITE_TITLE_URL.'admin/images/prod_image/'.$product_data['abpd_aplus_img']; ?>" target="_blank">View</a>&nbsp;&nbsp;&nbsp;
                  			<a href="javascript:void(0);" onClick="deleteAplusImg('<?php echo $product_data['abpd_id']?>');">Remove</a>
	                    <?php } ?>
	                </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Please provide YouTube video link related to this product which lead client to by this product :</label>
	                  	<input type="text"  name="abpd_video" id="abpd_video" value="<?php echo $product_data['abpd_video']; ?>" class="input-large">
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Is AgriBegri Fulfilment :</label>
	                    <input type="checkbox" name="abpd_agribegri_fulfilment" id="abpd_agribegri_fulfilment" value="Y" class="input-large" <?php echo ($product_data['abpd_agribegri_fulfilment'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Is google enable :</label>
	                    	<?php /*?><input type="checkbox" name="abpd_google_enable" id="abpd_google_enable" value="Y" class="input-large" <?php echo ($product_data['abpd_google_enable'] == 'Y') ? 'checked' : ''; ?>><?php */?>
                            	<?php
									$isgoogleEnable = $product_data['abpd_google_enable'];
									$googledisabled = "selected";
									$googleenabled="";
									$tsenabled = "";
									$generalenabled = "";
									$fbenabled = "";
									if($isgoogleEnable=="Y"){
										$googleenabled = "selected";
										$googledisabled="";
									}else if($isgoogleEnable=="TS"){
										$tsenabled = "selected";
										$googledisabled="";
									}else if($isgoogleEnable=="G"){
										$generalenabled = "selected";
										$googledisabled="";
									}else if($isgoogleEnable=="G"){
										$fbenabled = "selected";
										$googledisabled="";
									}else if($isgoogleEnable=="NC"){
										$ncenabled = "selected";
										$googledisabled="";
									}
								?>
                                <select id="abpd_google_enable" name="abpd_google_enable" class="input-medium">
                                	<option value="N" <?php echo $googledisabled; ?>>Select Option</option>
                                    <option value="Y" <?php echo $googleenabled; ?>>Google Enabled</option>
                                    <option value="TS" <?php echo $tsenabled; ?>>26% Enabled</option>
                                    <option value="G" <?php echo $generalenabled; ?>>General Enabled</option>
                                    <option value="FB" <?php echo $fbenabled; ?>>Facebook Enabled</option>
                                    <option value="NC" <?php echo $ncenabled; ?>>No Campaign</option>
                                </select>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Is facebook enable :</label>
	                    	<input type="checkbox" name="abpd_facebook_enable" id="abpd_facebook_enable" value="Y" class="input-large" <?php echo ($product_data['abpd_facebook_enable'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
                      <p>
	                  	<label>Season Month(s) :</label>
	                  	<select name="abpd_season_month[]" id="abpd_season_month" class="input-medium" multiple=''>
	                  		<?php	
	                				$exp_product = explode(',',$product_data['abpd_season_month']);
	                				foreach ($season_months as $key => $value) {
	                					if(in_array($key, $exp_product)){ 
												$selected = 'selected="selected"';
											}else{
												if(isset($product_data['abpd_id']) && $product_data['abpd_id'] > 0){
													$selected = '';
												}else{
													$selected = 'selected="selected"';
												}
											}
	                					//$selected = ($product_data['abpd_cropcat_id']==$crop_cat['ab_cat_id']) ? 'selected="	selected"' : '';
	                					?>
	                					<option <?php echo $selected; ?> value="<?php echo $key; ?>" ><?php echo $value; ?></option>
	                				<?php } ?>
	                    	</select>
	                  </p>
                      <p>&nbsp;</p>
	                  <p>
	                  	<label>Google product type :</label>
	                  	<textarea name="abpd_product_type" id="abpd_product_type" class="input-large extra-large-input"><?php echo $product_data['abpd_product_type']; ?></textarea>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Google product category :</label>
	                  	<textarea name="abpd_google_product_category" id="abpd_google_product_category" class="input-large extra-large-input"><?php echo $product_data['abpd_google_product_category']; ?></textarea>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Bharat agri margin (%) :</label>
	                  	<input type="text"  name="abpd_bharat_agri_margin_per" id="abpd_bharat_agri_margin_per" value="<?php echo $product_data['abpd_bharat_agri_margin_per']; ?>" class="input-large only-numeric" maxlength="4" autocomplete="off" />
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Having a color :</label>
	                    	<input type="checkbox" name="abpd_having_color" id="abpd_having_color" value="Y" class="input-large" <?php echo ($product_data['abpd_having_color'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p id="color_p" style="display: <?php if($product_data['abpd_having_color']=='Y'){ echo 'block';  }else{ echo 'none';  } ?>">
	                    <label>Color :</label>
	                    <input  name="abpd_product_color" id="abpd_product_color" class="input-file-cts inline style color: #011; border: 0px solid #333;" value="<?php echo $product_data['abpd_product_color']; ?>" >
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Out of stock :</label>
	                    	<input type="checkbox" name="abpd_out_of_stock" id="abpd_out_of_stock" value="Y" class="input-large">
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Country of Origin :</label>
	                  	<select name="ab_country_id" id="ab_country_id" class="select-large" >
	                  		<option value="">- - - Select Country - - -</option>
	                  		<?php	
	                  			$getCountryQry = "SELECT * FROM ab_countries ORDER BY name ASC";
	                				$getCountryQry = $pdo->_prepare($getCountryQry);
	                				$Country_res = $pdo->_resultset();
	                				foreach ($Country_res as $key => $country) {
	                					$selected = ($product_data['abpd_country_id']==$country['id']) ? 'selected="	selected"' : '';
	                					?>
	                					<option value="<?php echo $country['id']; ?>" <?php echo $selected; ?>><?php echo ucwords(strtolower($country['name'])); ?></option>
	                				<?php } ?>
	                    	</select>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Display Transport Variant :</label>
	                    	<input type="checkbox" name="abpd_display_transport_variant" id="abpd_display_transport_variant" value="Y" class="input-large" <?php echo ($product_data['abpd_display_transport_variant'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Allow Advance Payment :</label>
	                  	<select name="abpd_allow_advance_payment" id="abpd_allow_advance_payment" class="select-large" >
	                  		<option <?php if($product_data['abpd_allow_advance_payment']=='N'){ echo 'selected'; } ?> value="N">No</option>
	                  		<option <?php if($product_data['abpd_allow_advance_payment']=='Y'){ echo 'selected'; } ?> value="Y">Yes</option>
	                    	</select>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Google Crawl :</label>
	                    <input type="checkbox" name="abpd_google_crawl" id="abpd_google_crawl" value="Y" class="input-large" <?php echo ($product_data['abpd_google_crawl'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                    <label>Upload Default Image<span style="color:#FF0000;">*</span> :</label>
	                  	<input type="file" name="abpd_default_img" id="abpd_default_img">
	                  	<input type="hidden" name="abpd_old_default_img" id="abpd_old_default_img" value="<?php echo $product_data['abpd_image']; ?>">
	                  	<?php if($product_data['abpd_image']!=''){ ?>
	                  	<a href="<?php echo $SITE_TITLE_URL.'admin/images/'.$product_data['abpd_image']; ?>" target="_blank">View</a>&nbsp;&nbsp;&nbsp;
	                    <?php } ?>
	                  </p>
	                  <p>&nbsp;</p>
	                  <p>
	                  	<label>Farmers Only :</label>
	                    <input type="checkbox" name="abpd_farmers_only" id="abpd_farmers_only" value="Y" class="input-large" <?php echo ($product_data['abpd_farmers_only'] == 'Y') ? 'checked' : ''; ?>>
	                  </p>
	                  <p>&nbsp;</p>
	                  <table id="product_variation" class="table responsive table-striped">
	                  	<thead>
	                  		<tr>
		                  		<th>Title</th>
		                  		<th>Value</th>
		                  		<th>Image</th>
		                  	</tr>
	                  	</thead>
	                  	<tbody>
	                  		<?php if(mysqlNumRow($variation_unit) > 0){ $cnt = mysqlNumRow($variation_unit); }else{ $cnt = 1; } ?>
	                  		<input type="hidden" name="counter" id="counter" value="<?php echo $cnt; ?>">
	                  		<?php if(mysqlNumRow($variation_unit) > 0){ 
	                  			//$variationunit = mysqlFetchArray($variation_unit);
	                  			$i=1;
	                  			while($val = mysqlFetchArray($variation_unit)){ ?>
	                  				<tr id="variation_row<?php echo $i; ?>">
			                  			<td>
			                  				<select name="variation_title[]" id="variation_title<?php echo $i; ?>" class="select-large varition_title" onChange="title_variation(this.value,<?php echo $i; ?>);">
					                  			<option value="">Select Title</option>
					                  			<?php foreach ($product_variation as $key => $value) { ?>
					                  				<option <?php if($val['abpdu_title']==$key){ echo 'selected'; } ?> value="<?php echo $key; ?>"><?php echo $value; ?></option>
					                  			<?php } ?>
					                  		</select>
					                  	</td>
					                  	<td>
					                  		<input type="text" name="variation_val[]" id="variation_val<?php echo $i; ?>" value="<?php echo $val['abpdu_value']; ?>" class="input-large variation_val">
					                  	</td>
					                  	<td>
					                  		<input type="file" name="variation_file[]" id="variation_file<?php echo $i; ?>" value="" class="input-large" <?php if($val['abpdu_title']==1){ ?> style="display:block;"  <?php }else{ ?> style="display:none;"<?php } ?>>
					                  		<input type="hidden" name="old_image_id[]" id="old_image_id<?php echo $i; ?>" value="<?php echo $val['abpdu_image_id']; ?>">
					                  		<?php if($val['abpdu_image_id']!=0){ 
					                  				$sel_image = mysqlQuery("SELECT abpi_image FROM ab_product_image WHERE abpi_id = '".$val['abpdu_image_id']."' ");
					                  				if(mysqlNumRow($sel_image) > 0){ 
					                  				$sel_image_res = mysqlFetchArray($sel_image);
					                  				$product_unit_image = $sel_image_res['abpi_image']; ?>
					                  					<a href="images/<?php echo $product_unit_image; ?>" target="_blank"><i class="fa fa-eye" style="font-size: 18px;"></i></a>
					                  				<?php }
					                  			?>
					                  		<?php } ?>
					                  	</td>
					                  	<td>
					                  		<?php if($i==1){ ?>
					                  		<a href="javascript:void(0);" onClick="add_variation_val();"><i class="fa fa-plus" style="color: green;"></i></a>
					                  		<?php }else{ ?>
					                  			<a href="javascript:void(0);" onClick="remove_variation(<?php echo $i; ?>,<?php echo $val['abpdu_id']; ?>);"><i class="fa fa-trash"></i></a>
					                  		<?php } ?>
					                  	</td>
			                  		</tr>
	                  			<?php $i++; } ?>
	                  		<?php }else{ ?>
	                  		<tr id="variation_row1">
	                  			<td>
	                  				<select name="variation_title[]" id="variation_title1" class="select-large varition_title" onChange="title_variation(this.value,1);">
			                  			<option value="">Select Title</option>
			                  			<?php foreach ($product_variation as $key => $value) { ?>
			                  				<option value="<?php echo $key; ?>"><?php echo $value; ?></option>
			                  			<?php } ?>
			                  		</select>
			                  	</td>
			                  	<td>
			                  		<input type="text" name="variation_val[]" id="variation_val1" value="" onChange="get_color_val();" class="input-large variation_val">
			                  	</td>
			                  	<td>
			                  		<input type="file" name="variation_file[]" id="variation_file1" value="" class="input-large" style="display:none;">
			                  		<input type="hidden" name="old_image_id[]" id="old_image_id1" value="0">
			                  	</td>
			                  	<td>
			                  		<a href="javascript:void(0);" onClick="add_variation_val();"><i class="fa fa-plus" style="color: green;"></i></a>
			                  	</td>
	                  		</tr>
	                  		<?php } ?>
	                  	</tbody>
	                  </table>
	                  <p>&nbsp;</p>
	                  <!--hidden variables-->
	                  <table id="variationTable" class="table responsive table-striped">
	                  	<tbody>
	                  		<?php if(isset($product_data['variations']) && count($product_data['variations']) > 0){
								foreach ($product_data['variations'] as $vkey => $variation) { 
									$rowIndex = $vkey + 1;
									$varstatus = $variation['abpdv_status'];
									$varmainopen = "opened";
									$varbodyopen = "";
									$varsignopen = "-";
									$vardefault = "";
									$vardefaultcss = "";
									if($varstatus=="N"){
										$varmainopen = "closed";
										$varbodyopen = "style='display:none;'";
										$varsignopen = "+";
										$vardefault = "disabled";
										$vardefaultcss = 'style="color: #b3b2b2;"';
									}
									$tmpshippingcharge = ((floatval($variation['abpdv_post_charge_without_extra']) + floatval($variation['abpdv_post_charge_extra']))*100)/floatval($variation['abpdv_sale_price']);
									$comp_rate = mysqlFetchArray(mysqlQuery("SELECT abpc_amazone_rate,abpc_bighaat_rate FROM ab_product_competitor_rate WHERE abpc_variation_id = '".$variation['abpdv_id']."' ORDER BY abpc_id DESC LIMIT 1  "));
									if(!empty($comp_rate)){
										 if( ($variation['abpdv_sale_price'] > $comp_rate['abpc_amazone_rate'] && $comp_rate['abpc_amazone_rate']>0) || ($variation['abpdv_sale_price'] > $comp_rate['abpc_bighaat_rate'] && $comp_rate['abpc_bighaat_rate']>0)){
											$var_style = 'color:red';
										 }
									  }else{
										$var_style = '';
									  }
									?>
									<tr>
	                  					<input type="hidden" name="abpdv_id[]" value="<?php echo $variation['abpdv_id']; ?>" class="abpdv_id">
                                    	<input type="hidden" name="abpdv_old_price[]" value="<?php echo $variation['abpdv_price']; ?>" class="abpdv_price">
                                    	<input type="hidden" name="abpdv_old_shipped_by[]" value="<?php echo $variation['abpdv_shipped_by']; ?>" class="abpdv_shipped_by">
                                    	<input type="hidden" name="abpdv_old_qty[]" value="<?php echo $variation['abpdv_qty']; ?>" class="abpdv_old_qty">
                                    	<input type="hidden" name="abpd_old_name" value="<?php echo $product_data['abpd_name']; ?>" class="abpd_old_name">
                                    	<input type="hidden" name="abpdv_old_unit_quantity[]" value="<?php echo $variation['abpdv_unit_quantity']; ?>" class="abpdv_old_unit_quantity">
                                    	<input type="hidden" name="abpdv_old_unit[]" value="<?php echo $variation['abpdv_unit']; ?>" class="abpdv_old_unit">
                                    	<input type="hidden" name="abpdv_old_sale_price[]" value="<?php echo $variation['abpdv_sale_price']; ?>" class="abpdv_old_sale_price">
                                    	<input type="hidden" name="abpd_old_cat_id" value="<?php echo $product_data['abpd_cat_id']; ?>" class="abpd_old_cat_id">
                                    	<input type="hidden" name="abpdv_old_status[]" class="abpdv_old_status" value="<?php echo $variation['abpdv_status'];  ?>">
			                  			<td>
			                  				<table class="single_variation_group_wrap 11 <?php echo $varmainopen; ?> edit_variable">
			                  					<input type="hidden" class="var_count" value="<?php echo $rowIndex; ?>">
			                  					<thead>
			                  						<tr>
				                  						<td>
				                  							<h4>Variation <?php echo $rowIndex; ?> <?php if($variation['abpdv_unit_quantity']!=''){ echo ' - '.$variation['abpdv_unit_quantity']; } ?> <?php if($variation['abpdv_unit']!=''){ echo ' '.$variation['abpdv_unit']; } ?> <?php if($variation['abpdv_notes']!=''){ echo ' - '.$variation['abpdv_notes']; } ?></h4>
				                  						</td>
				                  						<td><span class="collaspe"><?php echo $varsignopen; ?></span></td>
				                  					</tr>
			                  					</thead>
			                  					<tbody <?php echo $varbodyopen; ?>>
			                  						<tr>
				                  						<td>
				                  							<p>
				                  								<label>Unit<span style="color:#FF0000;">*</span></label>
				                  								<select name="abpd_unit[]" class="abpd_unit select-large required" style="<?php echo setBorder($variation['abpdv_unit']); ?>">
				                  									<option value="">- - -Select Unit - - -</option>
											                      	<?php 
												                      	$units = get_product_units_list();
												                      	foreach ($units as $slug => $title) { 
												                      		$selected =  ($slug == $variation['abpdv_unit']) ? 'selected' : '';
											                      		?>
											                      		<option value="<?php echo $slug; ?>" <?php echo $selected; ?>><?php echo $title; ?></option>
											                      	<?php } ?>
				                  								</select>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Unit Quantity<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text"  name="abpd_unit_quantity[]" value="<?php echo $variation['abpdv_unit_quantity']; ?>" class="input-large numer_only abpd_unit_quantity required" style="<?php echo setBorder($variation['abpdv_unit_quantity']); ?>" >
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td >
				                  							<p>
				                  								<label>Delhivery ?</label>
				                  								<input type="checkbox" value="Y" name="delhivery_<?php echo $rowIndex ?>" class="abpdv_delhivery" <?php echo ($variation['abpdv_delhivery'] == 'Y') ? "checked" : ''; ?> >
				                  							</p>
				                  						</td>
				                  						<?php $sel_multi = mysqlQuery("SELECT abpd_multi_seller_product FROM ab_product WHERE abpd_id = '".$abpd_id."' AND abpd_multi_seller_product = 'Y' ");
				                  						if(mysqlNumRow($sel_multi) > 0){ ?>
					                  						<td>
					                  							<p>
					                  								<label>Seller</label>
					                  								<select name="abpdv_seller_id[]" class="select-large abpdv_seller_id" id="abpdv_seller_id_<?php echo $rowIndex;?>" data-rowindex="<?php echo $rowIndex;?>" onChange="get_seller_price(this.value,'<?php echo $variation['abpdv_id']; ?>','<?php echo $rowIndex;?>');">
					                  									<option value="">- - -Select Seller - - -</option>
												                      	<?php 
													                      	$sel_var = mysqlQuery("SELECT abs.absl_id,abs.absl_name FROM ab_multiseller_product abm JOIN ab_seller abs ON abs.absl_id = abm.seller_id WHERE abm.product_id = '".$abpd_id."' AND abm.variation_id = '".$variation['abpdv_id']."' AND absl_status = 'active' ");
													                      	while ($sel_res = mysqlFetchArray($sel_var)) { ?>
													                      		<option <?php if($variation['abpdv_seller_id']==$sel_res['absl_id']){ echo 'selected'; } ?> value="<?php echo $sel_res['absl_id']; ?>"><?php echo $sel_res['absl_name']; ?></option>
													                      	<?php } ?>
					                  								</select>
					                  							</p>
					                  						</td>
					                  					<?php } ?>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Price<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpd_price[]" value="<?php echo $variation['abpdv_price']; ?>" class="input-large numer_only abpd_price required" id="abpd_price_<?php echo $rowIndex;?>" data-rowindex="<?php echo $rowIndex;?>" style="<?php echo setBorder($variation['abpdv_price']); ?>" />
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Discount</label>
				                  								<input type="text" name="abpd_discount[]" value="<?php echo $variation['abpdv_discount']; ?>" class="input-large numer_only abpd_discount" id="abpd_discount_<?php echo $rowIndex;?>" data-rowindex="<?php echo $rowIndex;?>" />
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label style="<?php echo $var_style; ?>">Sale Price</label>
				                  								<input type="text" name="abpd_sale_price[]" value="<?php echo $variation['abpdv_sale_price']; ?>" class="input-large numer_only abpd_sale_price" id="abpdv_sale_price_<?= $rowIndex;?>" data-rowindex='<?= $rowIndex;?>' readonly>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>% Discount on MRP</label>
				                  								<input type="text" name="abpdv_mrp_discount[]" value="<?php echo $variation['abpdv_mrp_discount']; ?>" class="input-large numer_only abpdv_mrp_discount" readonly>
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Shipping Charge</label>
				                  								<input type="text" name="abpd_shipping_charge[]" value="<?php echo $variation['abpdv_shipping_charge']; ?>" class="input-large numer_only abpd_shipping_charge">
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Variation Code</label>
				                  								<input type="text" value="<?php echo $variation['abpdv_code']; ?>" class="input-large abpdv_code" readonly>
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Shipping Through<span style="color:#FF0000;">*</span></label>
				                  								<select name="abpdv_shipping_through[]" class="select-large abpdv_shipping_through" style="<?php echo setBorder($variation['abpdv_shipping_through']); ?>">
				                  								  <option value="">--Select Shipping Through--</option>
											                      <option value="Shipping Through Courier" <?php echo ($variation['abpdv_shipping_through'] == 'Shipping Through Courier') ? "selected" : ''; ?>>Shipping Through Courier</option>
											                      <option value="Shipping Through Indian" <?php echo ($variation['abpdv_shipping_through'] == 'Shipping Through Indian') ? "selected" : ''; ?>>Shipping Through Indian</option>
											                      <option value="Shipping Through Transport" <?php echo ($variation['abpdv_shipping_through'] == 'Shipping Through Transport') ? "selected" : ''; ?>>Shipping Through Transport, Charges Extra</option>
											                    </select>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Payment Method<span style="color:#FF0000;">*</span></label>
				                  								<select name="abpdv_payment_method[]" class="select-large abpdv_payment_method" style="<?php echo setBorder($variation['abpdv_payment_method']); ?>">
				                  								  <option value="">--Select Payment Method--</option>
											                      <option value="cod" <?php echo ($variation['abpdv_payment_method'] == 'cod') ? "selected" : ''; ?>>Cash on delivery</option>
											                      <option value="online" <?php echo ($variation['abpdv_payment_method'] == 'online') ? "selected" : ''; ?>>Online</option>
											                      <option value="both" <?php echo ($variation['abpdv_payment_method'] == 'both') ? "selected" : ''; ?>>Both</option>
											                    </select>
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Today Offer</label>
				                  								<select name="abpd_today_offer[]" class="select-large abpd_today_offer" >
											                      <option value="N" <?php echo ($variation['abpdv_today_offer'] == 'N') ? "selected" : ''; ?>>No</option>
											                      <option value="Y" <?php echo ($variation['abpdv_today_offer'] == 'Y') ? "selected" : ''; ?>>Yes</option>
											                    </select>
				                  							</p>
				                  						</td>
				                  						<td >
				                  							<p class="show_today_offer_active <?php echo ($variation['abpdv_today_offer'] == 'N') ? "hidden" : ''; ?>">
				                  								<label>Today Discount</label>
				                  								<input type="text" name="abpd_today_discount[]" value="<?php echo $variation['abpdv_today_discount']; ?>" class="input-large numer_only abpd_today_discount" >
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr class="show_today_offer_active <?php echo ($variation['abpdv_today_offer'] == 'N') ? "hidden" : ''; ?>">
				                  						<td>
				                  							<p>
				                  								<label>Till Date</label>
				                  								<input type="text" name="abpd_till_date[]" class="input-large abpd_till_date" value="<?php echo $variation['abpdv_till_date']!="1000-01-01"?$variation['abpdv_till_date']:""; ?>" />
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Till Time</label>
				                  								<input type="text" name="abpd_till_time[]"  class="input-large abpd_till_time" value="<?php echo $variation['abpdv_till_time']; ?>"/>
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<?php
				                  					$seller_type = seller_type($product_data['abpd_absupplier_id']);
				                  					if($seller_type != 'seller'){ $hidden = ""; }else{ $hidden = "hidden"; }
				                  						?>
				                  					<tr class="local_store_row <?php echo $hidden; ?>">
				                  						<!-- <td colspan="2" class="product_dimension"> -->
				                  						<td>
				                  							<p>
				                  								<label>Local Store Product?</label>
				                  								<select name="abpd_local_store_avail[]" class="select-large abpd_local_store_avail" >
				                  								<?php if($seller_type!='local_store_seller'){ ?>	
											                      <option value="N" <?php echo ($variation['abpdv_local_store_avail'] == 'N') ? "selected" : ''; ?>>No</option>
											                  	<?php } ?>
											                  	<?php if($seller_type!='seller'){ ?>
											                      <option value="Y" <?php echo ($variation['abpdv_local_store_avail'] == 'Y') ? "selected" : ''; ?>>Yes</option>
											                     <?php } ?>
											                    <?php if($seller_type=='both'){ ?>
											                      <option value="both" <?php echo ($variation['abpdv_local_store_avail'] == 'both') ? "selected" : ''; ?>>Both</option>
											                    <?php } ?>
											                    </select>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p class="show_local_store_avail <?php echo ($seller_type=='seller' || ($seller_type=='both') && $variation['abpdv_local_store_avail']=='N') ? "hidden" : ''; ?>">
				                  								<label>Local Store Discount</label>
				                  								<input type="text" name="abpd_local_store_dist[]" value="<?php echo $variation['abpdv_local_store_dist']; ?>" class="input-large numer_only abpd_local_store_dist">
				                  							</p>
				                  						</td>
				                  							<!-- <p class="show_local_store_avail <?php //echo ($variation['abpdv_local_store_avail'] == 'N') ? "hidden" : ''; ?>">
																<label>Allowed Postcodes :</label>
																<input  name="abpdv_allowed_postcode[]" id="abpdv_allowed_postcode" class="input-large"  value="<?php //echo $variation['abpdv_allowed_postcode']; ?>" >
															</p> -->
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Alert Quantity</label>
				                  								<input type="text" name="abpd_alert_quantity[]" value="<?php echo $variation['abpdv_alert_quantity']; ?>" class="input-large numer_only abpd_alert_quantity">
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Stock Quantity<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpd_qty[]" id="abpd_qty_<?php echo $rowIndex;?>" data-rowindex="<?php echo $rowIndex;?>" value="<?php echo $variation['abpdv_qty']; ?>" class="input-large numer_only abpd_qty required" style="<?php echo setBorder($variation['abpdv_qty']); ?>" maxlength='9'>
				                  								<?php 
				                  								$out_of_stock_qry ="SELECT apo_date FROM product_out_of_stock WHERE apo_prod_id='".$product_data['abpd_id']."' AND apo_prod_vari_id='".$variation['abpdv_id']."' AND `apo_prod_entry_for` = 'out_of_stock_entry'";
				                  								$out_of_stock_res = mysqlQuery($out_of_stock_qry);
				                  								$apo_date = ['------ Out stock ------'];
				                  								while ($out_of_stock = mysqlFetchArray($out_of_stock_res)) {
				                  									$apo_date[] = date('d/m/Y h:i:s A',strtotime($out_of_stock['apo_date'])); 				    
				                  								}
				                  								if(count($apo_date) > 0){
																	?>
																	<a href="javascript:void(0);" data-toggle="tooltip" title="<?php echo implode("\n", $apo_date); ?>" data-placement="right"><i class="fa fa-info-circle"></i></a>
																	<?php
																}
																$in_stock_qry ="SELECT apo_date FROM product_out_of_stock WHERE apo_prod_id='".$product_data['abpd_id']."' AND apo_prod_vari_id='".$variation['abpdv_id']."' AND `apo_prod_entry_for` = 'in_stock_entry'";
				                  								$in_stock_qry_res = mysqlQuery($in_stock_qry);
				                  								$apo_in_stock_date = ['------ In stock ------'];
				                  								while ($in_stock = mysqlFetchArray($in_stock_qry_res)) {
				                  									$apo_in_stock_date[] = date('d/m/Y h:i:s A',strtotime($in_stock['apo_date']));
				                  								}
				                  								if(count($apo_in_stock_date) > 0){
																	?>
																	<a href="javascript:void(0);" data-toggle="tooltip" title="<?php echo implode("\n", $apo_in_stock_date); ?>" data-placement="right" style="color: #0866c6 !important;"><i class="fa fa-info-circle"></i></a>
																	<?php
																}
																?>
				                  								<input type="hidden" name="old_abpd_qty[]" value="<?php echo $variation['abpdv_qty']; ?>">
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Product weight with packing in Grams<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpd_weight_packing[]" value="<?php echo $variation['abpdv_weight_packing']; ?>" class="input-file-cts numer_only abpd_weight_packing" style="<?php echo setBorder($variation['abpdv_weight_packing']); ?>">
				                  							</p>
				                  						</td>
				                  						<td style="display: none;">
				                  							<p>
				                  								<label>Courier fee<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpd_post_charge[]" value="<?php echo $variation['abpdv_post_charge']; ?>" class="input-file-cts abpd_post_charge numer_only required" style="<?php echo setBorder($variation['abpdv_post_charge']); ?>">
				                  							</p>
				                  						</td>
				                  						<td>&nbsp;</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>No of Boxes<span style="color:#FF0000;">*</span></label>
				                  								<input type="text" name="abpdv_no_boxes[]" value="<?php if(isset($variation['abpdv_no_boxes']) && $variation['abpdv_no_boxes'] != ''){ echo $variation['abpdv_no_boxes']; }?>" class="check_number abpdv_no_boxes">
				                  							</p>
				                  						</td>
				                  						<td>
															<p><label>Shipping <span class="vairant_shipping_per" id="vairant_shipping_per_<?php echo $rowIndex;?>" data-rowindex="<?php echo $rowIndex;?>"><?php echo number_format($tmpshippingcharge,2); ?></span>%</label></p>
														</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Courier Charges<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpdv_post_charge_without_extra[]" value="<?php echo $variation['abpdv_post_charge_without_extra']; ?>" class="input-file-cts abpdv_post_charge_without_extra numer_only required" id="abpdv_post_charge_without_extra_<?= $rowIndex;?>" data-rowindex='<?= $rowIndex;?>' style="<?php echo setBorder($variation['abpdv_post_charge_without_extra']); ?>">
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>COD or Bank Charges<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpdv_post_charge_extra[]" value="<?php echo $variation['abpdv_post_charge_extra']; ?>" class="input-file-cts abpdv_post_charge_extra numer_only required"  id="abpdv_post_charge_extra_<?= $rowIndex;?>" data-rowindex='<?= $rowIndex;?>' style="<?php echo setBorder($variation['abpdv_post_charge_extra']); ?>">
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>In Stock<span style="color:#FF0000;">*</span> </label>
				                  								<input type="hidden" name="old_abpd_in_stock[]" value="<?php echo $variation['abpdv_in_stock']; ?>" >
				                  								<input type="hidden" name="new_abpd_in_stock[]" class="new_abpd_in_stock" value="<?php echo $variation['abpdv_in_stock']; ?>" >
				                  								<select name="abpd_in_stock[]" id="abpd_in_stock_<?php echo $rowIndex;?>" data-rowindex="<?php echo $rowIndex;?>" class="select-large abpd_in_stock required" style="<?php echo setBorder($variation['abpdv_in_stock']); ?>" disabled='' >
				                  									<option value="">Select In Stock</option>
				                  									<option value="Y" <?php echo ($variation['abpdv_in_stock'] == 'Y') ? "selected" : ''; ?> >Yes</option>
				                  									<option value="N" <?php echo ($variation['abpdv_in_stock'] == 'N') ? "selected" : ''; ?> >No</option>
				                  								</select>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Minimum Order Quantity<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpd_min_order_qty[]" value="<?php echo ($variation['abpdv_min_order_qty']==0) ? '1' : $variation['abpdv_min_order_qty']; ?>" class="input-large numer_only abpd_min_order_qty required" style="<?php echo setBorder($variation['abpdv_min_order_qty']); ?>" maxlength='9'>
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>New Arrival Product</label>
				                  								<select name="abpd_new_arrival_product[]" class="select-large abpd_new_arrival_product" >
											                      <option value="N" <?php echo ($variation['abpdv_new_arrival_product'] == 'N') ? "selected" : ''; ?>>No</option>
											                      <option value="Y" <?php echo ($variation['abpdv_new_arrival_product'] == 'Y') ? "selected" : ''; ?>>Yes</option>
											                    </select>
				                  							</p>
				                  							<input type="hidden" name="abpd_display_on_home[]" value="N">
				                  						</td>

				                  						<?php /* ?><td>
				                  							<p>
				                  								<label>Display On Homepage</label>
				                  								<select name="abpd_display_on_home[]" class="select-large abpd_display_on_home" >
											                      <option value="N" <?php echo ($variation['abpdv_display_on_home'] == 'N') ? "selected" : ''; ?>>No</option>
											                      <option value="Y" <?php echo ($variation['abpdv_display_on_home'] == 'Y') ? "selected" : ''; ?>>Yes</option>
											                    </select>
				                  							</p>
				                  						</td><?php */ ?>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>MOQ for Quantity Discount</label>
				                  								<input type="text"  name="abpd_minimum_dealer_qty[]" value="<?php echo $variation['abpdv_minimum_dealer_qty']; ?>" class="input-large numer_only abpd_minimum_dealer_qty" >
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Quantity Discount (%)</label>
				                  								<input type="text" name="abpd_minimum_order_qty_discount[]" value="<?php echo $variation['abpdv_minimum_order_qty_discount']; ?>" class="input-large numer_only abpd_minimum_order_qty_discount" >
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<?php $checkTechQry = "SELECT abt_shipped_by FROM ab_product_technical WHERE abt_id = '".$product_data['abpd_technical_id']."'";
				                  									$checkTechQry = mysqlQuery($checkTechQry);
				                  									$checkTechRes = mysqlFetchArray($checkTechQry);
				                  									$shipped_technical_id = $checkTechRes['abt_shipped_by']; 
				                  									$checkCompaniesQry = "SELECT abc_branded FROM ab_company WHERE abc_id = '".$product_data['abpd_comp_id']."'";
				                  									$checkCompaniesQry = mysqlQuery($checkCompaniesQry);
				                  									$checkCompaniesRes = mysqlFetchArray($checkCompaniesQry);
				                  									$branded_company = $checkCompaniesRes['abc_branded'];
				                  									?>
				                  								<label>Shipped By (If you will Shipped then AgriBegri will charge 5% fee, If AgriBegri will Shipped then AgriBegri will charge 8% fee)<span style="color:#FF0000;">*</span> </label>
				                  								<input type="hidden" name="hidden_shipped_by[]" id="shipped_by" value="<?php echo $variation['abpdv_shipped_by']; ?>" class="hidden_shipped_by" >
				                  								<select name="shipped_by[]" class="select-large shipped_by required" style="<?php echo setBorder($variation['abpdv_shipped_by']); ?>" >
				                  									<?php if($access_array['aba_access_manage_product_shipped_by']['abaac_manage']=='Yes'){ 
				                  											$default_disabled = '';
									                        			}else{ 
									                        				if($shipped_technical_id=='' && $branded_company!='Y'){
									                        					$default_disabled = '';
									                        				}else{
									                        					$default_disabled = 'disabled'; 
									                        				}
									                        			} ?>
									                        	<option value="" <?php echo $default_disabled; ?>>--Select Shipped By ---</option>
									                        	<?php foreach ($agribegriShippedByArr as $key => $value) {
									                        			$selected =  ($key == $variation['abpdv_shipped_by']) ? 'selected' : '';
									                        			if($access_array['aba_access_manage_product_shipped_by']['abaac_manage']=='Yes'){
									                        				$disabled = '';
									                        			}else{
									                        				if($shipped_technical_id=='' && $branded_company!='Y'){
									                        					$disabled = '';
									                        				}else{
									                        					$disabled = ($key == $variation['abpdv_shipped_by']) ? '' : 'disabled'; 
									                        				}
									                        			}
									                        		?>
									                        			<option value="<?php echo $key; ?>" <?php echo $selected; ?> <?php echo $disabled; ?>><?php echo $value; ?></option>
									                        	<?php } ?>
									                        </select>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Amount Seller will get after Postage Fee, AgriBegri Fee and Dealer Commission</label>
				                  								<input type="text"  name="abpd_seller_amount[]" readonly value="<?php echo $variation['abpdv_seller_amount']; ?>" class="input-large numer_only abpd_seller_amount">
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td colspan="2" class="product_dimension">
				                  							<label style="width: 100%;">Product dimension in CM with packing</label>
				                  							<p>
					                  							<label>Length<span style="color:#FF0000;">*</span> </label>
					                  							<input type="text" name="abpd_length[]" value="<?php echo $variation['abpdv_length']; ?>" class="input-large numer_only abpd_length required" style="<?php echo setBorder($variation['abpdv_length']); ?>">
					                  						</p>
				                  							<p>
					                  							<label>Width<span style="color:#FF0000;">*</span> </label>
					                  							<input type="text" name="abpd_width[]" value="<?php echo $variation['abpdv_width']; ?>" class="input-large numer_only abpd_width required" style="<?php echo setBorder($variation['abpdv_width']); ?>">
					                  						</p>
					                  						<p>
				                  								<label>Height<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpd_height[]" value="<?php echo $variation['abpdv_height']; ?>" class="input-large numer_only abpd_height required" style="<?php echo setBorder($variation['abpdv_height']); ?>">
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Volumetric weight of parcel</label>
				                  								<input type="text" name="abpd_volumetric_weight[]" value="<?php echo $variation['abpdv_volumetric_weight']; ?>" class="input-file-cts numer_only abpd_volumetric_weight" readonly>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Agri Sale</label>
				                  								<select name="abpd_product_on_sale[]" class="select-large abpd_product_on_sale" >
				                  									<option value="">Select Agri Sale</option>
				                  									<option value="N" <?php echo ($variation['abpdv_product_on_sale'] == 'N') ? "selected" : ''; ?>>No</option>
											                      <option value="Y" <?php echo ($variation['abpdv_product_on_sale'] == 'Y') ? "selected" : ''; ?>>Yes</option>
				                  								</select>
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>Display Order<span style="color:#FF0000;">*</span> </label>
				                  								<input type="text" name="abpdv_display_order[]" value="<?php echo $variation['abpdv_display_order']; ?>" class="input-large numer_only abpdv_display_order required" style="<?php echo setBorder($variation['abpdv_display_order']); ?>" >
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<?php $unit_arr = array('gm','kg','liter','ml');
				                  							if(in_array($variation['abpdv_unit'], $unit_arr)){ 
				                  								if($variation['abpdv_status']=='Y'){
				                  									$req = 'required';
				                  								}else{
				                  									$req = '';
				                  								}
				                  								$stl = 'block'; $stl_txt = 'none'; 
				                  							}else{ $stl = 'none'; $stl_txt = 'block'; $req = ''; } ?>
				                  							<p class="drp_sel" style="display:<?php echo $stl; ?>">
				                  								<label>Notes</label>
				                  								<select name="abpdv_notes[]" class="abpdv_notes select-large" <?php echo $req; ?>>
						                  							<option value="">Select Notes</option> 
						                  							<?php $sel_unit = "SELECT abpvn_id,abpvn_notes FROM ab_product_variation_notes WHERE abpvn_unit = '".$variation['abpdv_unit']."' ORDER BY CAST(abpvn_notes AS UNSIGNED), abpvn_notes ";
																				$sel_unit_qry = mysqlQuery($sel_unit);
												                      	while ($sel_unit_res = mysqlFetchArray($sel_unit_qry)) { 
												                      		$selected = '';
												                      		if(trim($sel_unit_res['abpvn_notes'])==trim($variation['abpdv_notes'])){ $selected = 'selected'; }
												                      		//$selected =  (trim($sel_unit_res['abpvn_notes']) == trim($variation['abpdv_notes'])) ? 'selected' : ''; ?>
											                      		<option value="<?php echo $sel_unit_res['abpvn_notes']; ?>" <?php echo $selected; ?>><?php echo $sel_unit_res['abpvn_notes']; ?></option>
											                      	<?php } ?>
						                  						</select>
				                  								<?php /*?><input type="text" name="abpdv_notes[]" value="<?php echo $variation['abpdv_notes']; ?>" class="input-large abpdv_notes" maxlength="45"><?php */?>
                                                      <?php /* ?><textarea name="abpdv_notes[]" class="input-large abpdv_notes" maxlength="45"><?php echo $variation['abpdv_notes']; ?></textarea>
				                  								<span class="remains-character" style="margin-left:unset;"><?php echo "45 characters only" ?></span><?php */ ?>
				                  							</p>
				                  							<p class="note_txt" style="display:<?php echo $stl_txt; ?>">
																		<label>Notes</label>
																		<textarea name="abpdv_notes_txt[]" class="input-large abpdv_notes_txt" maxlength="255"><?php echo $variation['abpdv_notes']; ?></textarea>
				                  								<span class="remains-character" style="margin-left:unset;"><?php echo "255 characters only" ?></span>
																	</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label>A/C Name</label>
				                  								<input type="text" name="abpdv_ac_name[]" value="<?php echo $variation['abpdv_ac_name']; ?>" class="input-large abpdv_ac_name" style="" maxlength="255">
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>A/C Quantity</label>
				                  								<input type="text" name="abpdv_ac_quantity[]" value="<?php echo $variation['abpdv_ac_quantity']; ?>" class="input-large numer_only abpdv_ac_quantity" maxlength="255">
				                  							</p>
				                  						</td>
				                  					</tr>
                                             <tr>
				                  						<td>
				                  							<p>
				                  								<label>SKU to Use</label>
				                  								<select id="abpdv_sku_use" name="abpdv_sku_use[]" class="select-large abpdv_status">
                                                    		<option value="D" <?php echo ($variation['abpdv_sku_use'] == 'D' || $variation['abpdv_sku_use'] == '') ? "selected" : ''; ?>>Default</option>
                                                        <option value="A" <?php echo ($variation['abpdv_sku_use'] == 'A') ? "selected" : ''; ?>>Account</option>
                                                        <option value="O" <?php echo ($variation['abpdv_sku_use'] == 'O') ? "selected" : ''; ?>>Other</option>
                                                    	</select>
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Expiry Date</label>
				                  								<input type="text" name="abpdv_exp_date[]" value="<?php echo $variation['abpdv_exp_date']!='1000-01-01'?$variation['abpdv_exp_date']:''; ?>" class="input-large abpdv_exp_date">
				                  							</p>
				                  						</td>
				                  					</tr>
                                             <tr>
				                  						<td>
				                  							<p>
				                  								<label>Other SKU</label>
				                  								<input type="text" name="abpdv_other_sku[]" value="<?php echo $variation['abpdv_sku_other_code']; ?>" class="input-large abpdv_other_sku" style="" maxlength="20">
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Other SKU Qty</label>
				                  								<input type="text" name="abpdv_other_qty[]" value="<?php echo $variation['abpdv_sku_other_qty']; ?>" class="input-large numer_only abpdv_other_qty" maxlength="3">
				                  							</p>
				                  						</td>
				                  					</tr>
				                  					<tr>
				                  						<td>
				                  							<p>
				                  								<label <?php echo $vardefaultcss; ?> >Is Default</label>
				                  								<input type="checkbox" value="Y" name="is_default_variation_<?php echo $rowIndex ?>" class="is_default_variation" <?php echo ($variation['abpdv_is_default'] == 'Y') ? "checked" : ''; ?> <?php echo $vardefault; ?> >
				                  							</p>
				                  						</td>
				                  						<td>
				                  							<p>
				                  								<label>Status</label>
				                  								<select name="abpdv_status[]" class="select-large abpdv_status" >
				                  									<option value="Y" <?php echo ($variation['abpdv_status'] == 'Y') ? "selected" : ''; ?>>Active</option>
				                  									<option value="N" <?php echo ($variation['abpdv_status'] == 'N') ? "selected" : ''; ?>>InActive</option>
											                    </select>
				                  							</p>
				                  						</td>
				                  					</tr>
													<tr>
														<td>
															<p>
																<label>Dispaly for </label>
																<select name="abpdv_display_for[]" id="abpdv_display_for" class="select-large" >
																	<option value="agribegri" <?php echo ($variation['abpdv_display_for'] == 'agribegri') ? "selected" : ''; ?> >AgriBegri</option>
																	<option value="bharat_agri" <?php echo ($variation['abpdv_display_for'] == 'bharat_agri') ? "selected" : ''; ?> >Bharat Agri</option>
																	<option value="both" <?php echo ($variation['abpdv_display_for'] == 'both') ? "selected" : ''; ?> >Both</option>
																</select>
															</p>
														</td>
														<td>
		                  							<p>
																<label>Image</label>
																<input type="file" name="abpdv_variation_img[]" id="abpdv_variation_img">
																<input type="hidden" name="old_variation_image_id[]" value="<?php echo $variation['abpdv_variation_img']; ?>">
																<?php if($variation['abpdv_variation_img'] != 0 && $variation['abpdv_variation_img']!=''){ 
																	$sel = mysqlFetchArray(mysqlQuery("SELECT abpi_image FROM ab_product_image WHERE abpi_id = '".$variation['abpdv_variation_img']."' "));
																	?>
																	<a href="<?php echo 'images/'.$sel['abpi_image']; ?>" target="_blank"><i class="fa fa-eye"></i></a>
																<?php } ?>
															</p>
		                  						</td>
													</tr>
                                       <tr>
                                          <td>
															<p>
																<label>Product Code </label>
																<input type="text" name="product_code[]" value="" class="input-large" >
															</p>
														</td>
														<td>
		                  							<p>
		                  								<label>Rate Compare</label>
		                  								<input type="text" name="abpdv_rate_compare[]" value="<?php echo $variation['abpdv_rate_compare']; ?>" class="input-large numer_only">
		                  							</p>
		                  						</td>
                                       </tr>
                                       <tr>
                                       	<?php $exp_color = explode(',',$variation['abpdv_variation_color']); ?>
														<td>
		                  							<p>
																<label>Color</label>
																<select name="abpdv_variation_color_<?php echo $rowIndex ?>[]" data-rowindex="<?php echo $rowIndex ?>" id="abpdv_variation_color_<?php echo $rowIndex ?>" class="select-large abpdv_variation_color" multiple >
																	<?php 
																	$variation_unit = mysqlQuery("SELECT * FROM ab_product_variation_unit WHERE abpdu_product_id = '".$_GET['abpd_id']."' ");
																	while($val = mysqlFetchArray($variation_unit)){
																		if($val['abpdu_title']==1){
																			if(in_array($val['abpdu_value'],$exp_color)){
																				$sel = 'selected';
																			}else{
																				$sel = '';
																			}
																		 ?>
			                  									<option <?php echo $sel;  ?> value="<?php echo $val['abpdu_value']; ?>"><?php echo $val['abpdu_value']; ?></option>
			                  								<?php } } ?>
																</select>
															</p>
		                  						</td>
		                  						<?php if($product_data['abpd_agribegri_fulfilment']=='Y'){ $fulfill_readonly = ''; }else{ $fulfill_readonly = 'none'; } ?>
		                  						<input type="hidden" class="hidden_fulfilment_amount" value="<?php echo $variation['abpdv_fulfilment_amount']; ?>">
		                  						<td class="td_fulfilment_amount" style="display:<?php echo $fulfill_readonly; ?>">
		                  							<p>
																<label>Fulfilment Amount</label>
																<input type="text" name="abpdv_fulfilment_amount[]" value="<?php echo $variation['abpdv_fulfilment_amount']; ?>" class="input-large numer_only abpdv_fulfilment_amount" readonly>
															</p>
		                  						</td>
                                       </tr>
				                  					<tr>
				                  						<td>&nbsp;</td>
				                  						<td class="add_remove_variaiton_wrap">
				                  							<button type="button" class="btn btn-primary btn-large add_variation">Add Variation</button>
				                  						</td>
				                  					</tr>
				                  				</tbody>
				                  			</table>
				                  		</td>
				                  	</tr>
	                  			<?php } ?>
	                  		<?php }else{ ?>
	                  			<tr>
		                  			<td>
		                  				<table class="single_variation_group_wrap 22 opened add_variable">
		                  					<input type="hidden" class="var_count" value="1">
		                  					<thead>
		                  						<tr>
			                  						<td>
			                  							<h4>Variation 1</h4>
			                  						</td>
			                  						<td><span class="collaspe">-</span></td>
			                  					</tr>
		                  					</thead>
		                  					<tbody>
		                  						<tr>
		                  							<input type="hidden" name="abpdv_id[]" value="">
                            						<input type="hidden" name="abpdv_old_price[]" value="">
                                    				<input type="hidden" name="abpdv_old_shipped_by[]" value="">
                            						<input type="hidden" name="abpdv_old_qty[]" value="">
			                  						<td>
			                  							<p>
			                  								<label>Unit<span style="color:#FF0000;">*</span></label>
			                  								<select name="abpd_unit[]" class="abpd_unit select-large required" style="<?php echo setBorder(''); ?>">
			                  									<option value="">- - -Select Unit - - -</option>
										                      <?php 
											                      	$units = get_product_units_list();
											                      	foreach ($units as $slug => $title) { 
											                      	?>
										                      		<option value="<?php echo $slug; ?>" <?php echo $selected; ?>><?php echo $title; ?></option>
										                      	<?php } ?>
			                  								</select>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Unit Quantity<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text"  name="abpd_unit_quantity[]" value="" class="input-large numer_only abpd_unit_quantity required" style="<?php echo setBorder(''); ?>" >
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Delhivery ?</label>
			                  								<input type="checkbox" value="Y" name="delhivery_1" class="abpdv_delhivery">
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Price<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpd_price[]" value="" class="input-large numer_only abpd_price required" id="abpd_price_1" style="<?php echo setBorder(''); ?>" data-rowindex="1" />
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Discount</label>
			                  								<input type="text" name="abpd_discount[]" value="" class="input-large numer_only abpd_discount" id="abpd_discount_1" data-rowindex="1" />
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Sale Price</label>
			                  								<input type="text" name="abpd_sale_price[]" value="" class="input-large numer_only abpd_sale_price" id="abpdv_sale_price_1" data-rowindex='1' readonly>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
				                  							<label>% Discount on MRP</label>
					                  						<input type="text" name="abpdv_mrp_discount[]" value="" class="input-large numer_only abpdv_mrp_discount" readonly>
					                  					</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Shipping Charge</label>
			                  								<input type="text" name="abpd_shipping_charge[]" value="" class="input-large numer_only abpd_shipping_charge">
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Variation Code</label>
			                  								<input type="text" value="" class="input-large abpdv_code" readonly>
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Shipping Through<span style="color:#FF0000;">*</span></label>
			                  								<select name="abpdv_shipping_through[]" class="select-large abpdv_shipping_through" style="<?php echo setBorder(''); ?>">
			                  								  <option value="">--Select Shipping Through--</option>
										                      <option value="Shipping Through Courier">Shipping Through Courier</option>
										                      <option value="Shipping Through Indian">Shipping Through Indian</option>
										                      <option value="Shipping Through Transport">Shipping Through Transport, Charges Extra</option>
										                    </select>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Payment Method<span style="color:#FF0000;">*</span></label>
			                  								<select name="abpdv_payment_method[]" class="select-large abpdv_payment_method" style="<?php echo setBorder(''); ?>">
			                  								  <option value="">--Select Payment Method--</option>
										                      <option value="cod">Cash on delivery</option>
										                      <option value="online">Online</option>
										                      <option value="both">Both</option>
										                    </select>
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Today Offer</label>
			                  								<select name="abpd_today_offer[]" class="select-large abpd_today_offer" >
										                      <option value="N">No</option>
										                      <option value="Y">Yes</option>
										                    </select>
			                  							</p>
			                  						</td>
			                  						<td >
			                  							<p class="show_today_offer_active hidden">
			                  								<label>Today Discount</label>
			                  								<input type="text" name="abpd_today_discount[]" value="" class="input-large numer_only abpd_today_discount" >
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr class="show_today_offer_active hidden">
			                  						<td>
			                  							<p>
			                  								<label>Till Date</label>
			                  								<input type="text" name="abpd_till_date[]" class="input-large abpd_till_date" value="" />
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Till Time</label>
			                  								<input type="text" name="abpd_till_time[]"  class="input-large abpd_till_time" value=""/>
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr class="local_store_row">
			                  						<!-- <td colspan="2" class="product_dimension"> -->
			                  						<td>
			                  							<p>
			                  								<label>Local Store Product?</label>
			                  								<select name="abpd_local_store_avail[]" class="select-large abpd_local_store_avail" >
										                      <option value="N">No</option>
										                      <option value="Y">Yes</option>
										                      <option value="both">Both</option>
										                    </select>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p class="show_local_store_avail">
			                  								<label>Local Store Discount</label>
			                  								<input type="text" name="abpd_local_store_dist[]" value="" class="input-large numer_only abpd_local_store_dist">
			                  							</p>
			                  						</td>
			                  							<!-- <p class="show_local_store_avail hidden">
																<label>Allowed Postcodes :</label>
																<input  name="abpdv_allowed_postcode[]" id="abpdv_allowed_postcode" class="input-large"  value="" >
														</p> -->
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Alert Quantity</label>
			                  								<input type="text" name="abpd_alert_quantity[]" value="" class="input-large numer_only abpd_alert_quantity">
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Stock Quantity<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpd_qty[]" id="abpd_qty_1" data-rowindex="1" value=""class="input-large numer_only abpd_qty required" style="<?php echo setBorder(''); ?>" maxlength='9'>
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Product weight with packing in Grams<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpd_weight_packing[]" value="" class="input-file-cts numer_only abpd_weight_packing" style="<?php echo setBorder(''); ?>">
			                  							</p>
			                  						</td>
			                  						<td style="display: none;">
			                  							<p>
			                  								<label>Courier fee<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpd_post_charge[]" value="" class="input-file-cts abpd_post_charge numer_only required" style="<?php echo setBorder(''); ?>">
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>No of Boxes<span style="color:#FF0000;">*</span></label>
			                  								<input type="text" name="abpdv_no_boxes[]" value="1.00" class="check_number abpdv_no_boxes" >
			                  							</p>
			                  						</td>
			                  						<td>
														<p><label>Shipping <span class="vairant_shipping_per" id="vairant_shipping_per_1" data-rowindex="1">0</span>%</label></p>
													</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Courier Charges<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpdv_post_charge_without_extra[]" class="input-file-cts abpdv_post_charge_without_extra numer_only required" id="abpdv_post_charge_without_extra_1" data-rowindex='1' style="<?php echo setBorder(''); ?>">
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>COD or Bank Charges<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpdv_post_charge_extra[]" value="" class="input-file-cts abpdv_post_charge_extra numer_only required" id="abpdv_post_charge_extra_1" data-rowindex='1' style="<?php echo setBorder(''); ?>">
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>In Stock<span style="color:#FF0000;">*</span> </label>
			                  								<select name="abpd_in_stock[]" id="abpd_in_stock_1" data-rowindex="1" class="select-large abpd_in_stock required" style="<?php echo setBorder(''); ?>" disabled='' >
			                  									<option value="">Select In Stock</option>
			                  									<option value="Y">Yes</option>
			                  									<option value="N">No</option>
			                  								</select>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Minimum Order Quantity<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpd_min_order_qty[]" value="1" class="input-large numer_only abpd_min_order_qty required" style="<?php echo setBorder(''); ?>" maxlength='9'>
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>New Arrival Product</label>
			                  								<select name="abpd_new_arrival_product[]" class="select-large abpd_new_arrival_product" >
										                      <option value="N">No</option>
										                      <option value="Y">Yes</option>
										                    </select>
			                  							</p>
			                  							<input type="hidden" name="abpd_display_on_home[]" value="N">
			                  						</td>
			                  						<?php /* ?><td>
			                  							<p>
			                  								<label>Display On Homepage</label>
			                  								<select name="abpd_display_on_home[]" class="select-large abpd_display_on_home" >
										                      <option value="N">No</option>
										                      <option value="Y">Yes</option>
										                    </select>
			                  							</p>
			                  						</td><?php */ ?>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>MOQ for Quantity Discount</label>
			                  								<input type="text"  name="abpd_minimum_dealer_qty[]" value="" class="input-large numer_only abpd_minimum_dealer_qty" >
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Quantity Discount (%)</label>
			                  								<input type="text" name="abpd_minimum_order_qty_discount[]" value="" class="input-large numer_only abpd_minimum_order_qty_discount" >
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<input type="hidden" name="hidden_shipped_by[]" id="shipped_by" class="hidden_shipped_by" value="" >
			                  								<label>Shipped By (If you will Shipped then AgriBegri will charge 5% fee, If AgriBegri will Shipped then AgriBegri will charge 8% fee)<span style="color:#FF0000;">*</span> </label>
			                  								<select name="shipped_by[]" class="select-large shipped_by required" style="<?php echo setBorder(''); ?>" >
								                        	<option value="">--Select Shipped By ---</option>
								                        	<?php foreach ($agribegriShippedByArr as $key => $value) { ?>
								                        			<option value="<?php echo $key; ?>" ><?php echo $value; ?></option>
								                        	<?php } ?>
								                        </select>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Amount Seller will get after Postage Fee, AgriBegri Fee and Dealer Commission</label>
			                  								<input type="text"  name="abpd_seller_amount[]" readonly value="" class="input-large numer_only abpd_seller_amount">
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td colspan="2" class="product_dimension">
			                  							<label style="width: 100%;">Product dimension in CM with packing</label>
			                  							<p>
				                  							<label>Length<span style="color:#FF0000;">*</span> </label>
				                  							<input type="text" name="abpd_length[]" value="" class="input-large numer_only abpd_length required" style="<?php echo setBorder(''); ?>">
				                  						</p>
			                  							<p>
				                  							<label>Width<span style="color:#FF0000;">*</span> </label>
				                  							<input type="text" name="abpd_width[]" value="" class="input-large numer_only abpd_width required" style="<?php echo setBorder(''); ?>">
				                  						</p>
				                  						<p>
			                  								<label>Height<span style="color:#FF0000;">*</span> </label>
			                  								<input type="text" name="abpd_height[]"value="" class="input-large numer_only abpd_height required" style="<?php echo setBorder(''); ?>">
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Volumetric weight of parcel</label>
			                  								<input type="text" name="abpd_volumetric_weight[]" value="" class="input-file-cts numer_only abpd_volumetric_weight" readonly>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Agri Sale</label>
			                  								<select name="abpd_product_on_sale[]" class="select-large abpd_product_on_sale" >
			                  									<option value="">Select Agri Sale</option>
			                  									<option value="N">No</option>
			                  									<option value="Y">Yes</option>
			                  								</select>
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
													<td>
														<p>
															<label>Display Order<span style="color:#FF0000;">*</span> </label>
															<input type="text" name="abpdv_display_order[]" value="" class="input-large numer_only abpdv_display_order required" style="<?php echo setBorder(''); ?>" >
														</p>
													</td>
													<td>
														<p class="drp_sel" style="display: none;">
															<label>Notes</label>
															<select name="abpdv_notes[]" class="abpdv_notes select-large">
			                  							<option value="">Select Notes</option> 
			                  						</select>
															<?php /*?><input type="text" name="abpdv_notes[]" value="" class="input-large abpdv_notes" maxlength="45"><?php */?>
                                             <?php /* ?><textarea name="abpdv_notes[]" class="input-large abpdv_notes" maxlength="45"></textarea>
															<span class="remains-character" style="margin-left:unset;"><?php echo "45 characters only"?></span><?php */ ?>
														</p>
														<p class="note_txt">
															<label>Notes</label>
															<textarea name="abpdv_notes_txt[]" class="input-large abpdv_notes_txt" maxlength="255"></textarea>
															<span class="remains-character" style="margin-left:unset;"><?php echo "255 characters only"?></span>
															<?php /*?><input type="text" name="abpdv_notes[]" value="" class="input-large abpdv_notes" maxlength="45"><?php */?>
                                             <?php /* ?><textarea name="abpdv_notes[]" class="input-large abpdv_notes" maxlength="45"></textarea>
															<span class="remains-character" style="margin-left:unset;"><?php echo "45 characters only"?></span><?php */ ?>
														</p>
													</td>
												</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>A/C Name</label>
			                  								<input type="text" name="abpdv_ac_name[]" value="" class="input-large abpdv_ac_name" style="" maxlength="255">
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>A/C Quantity</label>
			                  								<input type="text" name="abpdv_ac_quantity[]" value="" class="input-large abpdv_ac_quantity numer_only" maxlength="255">
			                  							</p>
			                  						</td>
			                  					</tr>
                                          <tr>
			                  						<td>
			                  							<p>
			                  								<label>SKU to Use</label>
			                  								<select id="abpdv_sku_use" name="abpdv_sku_use[]" class="select-large abpdv_status">
	                                              	  <option value="D">Default</option>
	                                                  <option value="A">Account</option>
	                                                  <option value="O">Other</option>
	                                              	</select>
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Expiry Date</label>
			                  								<input type="text" name="abpdv_exp_date[]" value="" class="input-large abpdv_exp_date">
			                  							</p>
			                  						</td>
			                  					</tr>
                                          <tr>
			                  						<td>
			                  							<p>
			                  								<label>Other SKU</label>
			                  								<input type="text" name="abpdv_other_sku[]" value="" class="input-large abpdv_other_sku" style="" maxlength="20">
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
			                  								<label>Other SKU Qty</label>
			                  								<input type="text" name="abpdv_other_qty[]" value="" class="input-large numer_only abpdv_other_qty" maxlength="3">
			                  							</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
			                  								<label>Is Default</label>
			                  								<input type="checkbox" value="Y" name="is_default_variation_1" class="is_default_variation">
			                  							</p>
			                  						</td>
			                  						<td>
			                  							<p>
															<label>Status</label>
															<select name="abpdv_status[]" class="select-large abpdv_status" >
																<option value="Y">Active</option>
																<option value="N">InActive</option>
															</select>
														</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
																	<label>Dispaly for</label>
																	<select name="abpdv_display_for[]" id="abpdv_display_for" class="select-large" >
																		<option value="agribegri">AgriBegri</option>
																		<option value="bharat_agri">Bharat Agri</option>
																		<option value="both">Both</option>
																	</select>
																</p>
			                  						</td>
															<td>
			                  							<p>
																	<label>Image</label>
																	<input type="file" name="abpdv_variation_img[]" id="abpdv_variation_img">
																</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>
			                  							<p>
																	<label>Color</label>
																	<select name="abpdv_variation_color_1[]" data-rowindex="1" id="abpdv_variation_color_1" class="select-large abpdv_variation_color" multiple >
																	</select>
																</p>
			                  						</td>
			                  						<input type="hidden" class="hidden_fulfilment_amount" value="0">
			                  						<td class="td_fulfilment_amount" style="display:none;">
			                  							<p>
																	<label>Fulfilment Amount</label>
																	<input type="text" name="abpdv_fulfilment_amount[]" value="" class="input-large numer_only abpdv_fulfilment_amount" readonly>
																</p>
			                  						</td>
			                  					</tr>
			                  					<tr>
			                  						<td>&nbsp;</td>
			                  						<td class="add_remove_variaiton_wrap">
			                  							<button type="button"class="btn btn-primary btn-large add_variation">Add Variation</button>
			                  						</td>
			                  					</tr>
			                  				</tbody>
			                  			</table>
			                  		</td>
			                  	</tr>
	                  		<?php } ?>
	                  	</tbody>
	                  </table>
	                  <p>
	                    <label>&nbsp;</label>
	                    <input type="hidden" id="glob_con_status" value="0">
	                    <input type="submit" style="display: none;" name="submit" value="save" id="btn_frm_n_submit">
	                    <input type="button" name="submit" class="btn btn-primary btn-large" value="Save & Continue" id="btn_frm_submit">
	                    <input type="submit" style="display: none;" name="submit" value="save_draft" id="btn_save_n_draft">
	                    <input type="button" name="submit" class="btn btn-primary btn-large" value="Save & Draft" id="btn_save_draft">
	                  </p>
	                </form>
	              </div> <!-- END OF widgetcontent -->
	            </div> <!-- END OF widgetbox -->
	          </div> <!-- END OF span8 -->
	          <div class="span4">
	          	<!-- UPLOAD IMAGE SECTION -->
	            <div class="widgetbox">
	              <div class="headtitle">
	                <div class="btn-group">
	                  <button class="btn dropdown-toggle" data-toggle="dropdown" id="toggle-action" style="padding:11px 20px 5px 20px; background:#bb2f0e;">Action <span class="caret"></span></button>
	                  <ul class="dropdown-menu">
	                  	<?php $upldImgProdId = ($_GET["abpd_id"]>0) ? $_GET["abpd_id"] : $_SESSION['rand_idads']; ?>
	                    <li><a href="javascript:void(0)" rel="upload_product_image_popup.php?abpd_id=<?php echo $upldImgProdId; ?>" class="uplaod_image" >Upload Image</a></li>
	                  </ul>
	                </div> <!-- END OF btn-group -->
	                <h4 class="widgettitle">Add Product Images</h4>
	              </div> <!-- END OF headtitle -->
	              <div id="image_con" class="widgetcontent" ><?php require('productimages.php');?> 
	            	</div> <!-- END OF image_con -->
	            </div> <!-- END OF widgetbox -->
	            <!-- RECOMANDAD PRODUCTS SECTOIN -->
	            <div class="widgetbox">
	              <div class="headtitle">
	                <h4 class="widgettitle">Select Recommanded Products</h4>
	              </div>
					<div id="recommanded_prod" class="widgetcontent" >
						<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
						    <table class="table table-bordered responsive table-striped" id="recomandedn_table" >
						      <colgroup>
						      	<col class="con0" style="width:10%;">
						      	<col class="con1" style="width:90%;">
						      </colgroup>
						      <thead>
						      	<th>Select</th>
						      	<th>Product Name</th>
						      </thead>
						      <tbody>
						      	<?php 
						      		/*** get all productids and name from product table ***/
						      		$activeProdsQry = "SELECT abpd_id, abpd_name FROM ab_product WHERE seller_sale_status = 'Y' AND abpd_active='Y' AND abpd_id NOT IN ('".$product_data['abpd_id']."') ORDER BY abpd_id DESC";
						      		$activeProdsQry = $pdo->_prepare($activeProdsQry);
						      		$activeProds = $pdo->_resultset();
						      		/*** get recommanded products id of current edited prods ****/
						      		if(isset($product_data['abpd_id']) && $product_data['abpd_id'] != ''){
						      			$recmdProdQry = "SELECT GROUP_CONCAT(abpr_recomded_product_id) as abpr_recomded_product_id FROM `ab_product_recomanded_products` WHERE `abpr_product_id` = $abpd_id";
						        		$recmdProdQry = $pdo->_prepare($recmdProdQry);
						        		$recmdProds = $pdo->_resultset();
						        		$recmdProdslist = $recmdProds[0]['abpr_recomded_product_id'];
						        		$recmdProds = explode(',', $recmdProdslist);
						      		}
						      		foreach ($activeProds as $rec_prod) {
						      			$checked = (isset($recmdProds) && in_array($rec_prod['abpd_id'],$recmdProds) ) ? 'checked' : '';
						      			?>
						      			<tr id="<?php echo $rec_prod['abpd_id']; ?>"> 
						              		<td>
						              			<input form="prdct_frm" type="checkbox" name="abpd_recomanded_prod[]" class="abpd_recomanded_prod" value="<?php echo $rec_prod['abpd_id']; ?>" <?php echo $checked; ?>>
						              		</td>
						              		<td><?php echo $rec_prod['abpd_name']; ?></td>
						            	</tr>
						      		<?php } ?>
						        </tbody>
						    </table>
						</div> <!-- END OF recomadend_wrap -->
						<div id="recommandedProd"></div>
					</div> <!-- END OF widgetcontent -->
	            </div>
	            <!-- END OF widgetbox -->
	            <!-- Similar Product START -->
	            <?php
				// similar product ni main product ne highlights karva mate nu che end
				$product_Arr 		= '';
				$similar_product_id = '';
				$similar_add_id 	= '';
				$similarIdProds 	= array();
				$unique_product 	= array();
				if(isset($product_data['abpd_id']) && $product_data['abpd_id'] != ''){
					$similar_id_get = "SELECT abps_product_id FROM `ab_product_similar_products` WHERE abps_similar_product_id IN (".$abpd_id.")";
					if($_REQUEST['debug']==1){
						echo "<br> similar_id_get:".$similar_id_get;
					}
					$pdo->_prepare($similar_id_get);
					$similarIdProds = $pdo->_singlerow();
					$main_product = $similarIdProds['abps_product_id'];
					if($main_product == ''){
						$add_ids = $product_data['abpd_id'];
					}else{
						$add_ids = $main_product;
					}
					$recmdProdQryRe = "SELECT GROUP_CONCAT(abps_similar_product_id) as abps_similar_product_id FROM `ab_product_similar_products` WHERE `abps_product_id` = ".$add_ids." AND abps_similar_product_id NOT IN (".$product_data['abpd_id'].")";
					$recmdProdQryRe = $pdo->_prepare($recmdProdQryRe);
					$recmdProdsReRecord = $pdo->_resultset();
					$recmdProdslistRe = $recmdProdsReRecord[0]['abps_similar_product_id'];
					if(($main_product != '' && $main_product != NULL) && ($recmdProdslistRe != '' && $recmdProdslistRe != NULL)){
						$product_Arr = $main_product.','.$recmdProdslistRe;
						echo "<input form='prdct_frm' type='hidden' name='main_product' id='main_product' value='".$main_product."'>";
					}else if($recmdProdslistRe != '' && $recmdProdslistRe != NULL){
						$product_Arr = $recmdProdslistRe;
						echo "<input form='prdct_frm' type='hidden' name='main_product' id='main_product' value=''>";
					}else{
						$product_Arr = $main_product;
						echo "<input form='prdct_frm' type='hidden' name='main_product' id='main_product' value='".$main_product."'>";
					}
					$recmdProdsRe = explode(',', $product_Arr);
					// it's remove product and similar product id current abpd_id regarding and show other ids start
					if(isset($abpd_id) && $abpd_id != ''){
						// when in product
						$similar_id_get = "SELECT abps_product_id, abps_similar_product_id FROM `ab_product_similar_products` WHERE abps_product_id NOT IN (".$abpd_id.")";
						$pdo->_prepare($similar_id_get);
						$similarIdProds = $pdo->_resultset();
					}
					$products = array();
					if(!empty($similarIdProds)){
						foreach ($similarIdProds as $key => $value) {
							$products[] = $value['abps_product_id'];
							$products[] = $value['abps_similar_product_id'];
						}
					}
					$unique_product = array_unique($products);
					foreach ($recmdProdsRe as $key => $value) {
						if (($key = array_search($value, $unique_product)) !== false) {
						    unset($unique_product[$key]);
						}	
					}
					if(!empty($unique_product)){
						$similar_product_id = implode(',', $unique_product);
						$similar_add_id = ','.$similar_product_id;
					}
					// it's remove product and similar product id current abpd_id regarding and show other ids end
				}else{
					$similar_id_get = "SELECT abps_product_id, abps_similar_product_id FROM `ab_product_similar_products` ";
					$pdo->_prepare($similar_id_get);
					$similarIdProds = $pdo->_resultset();
					$products = array();
					if(!empty($similarIdProds)){
						foreach ($similarIdProds as $key => $value) {
							$products[] = $value['abps_product_id'];
							$products[] = $value['abps_similar_product_id'];
						}
					}
					$unique_product = array_unique($products);
					if(!empty($unique_product)){
						$similar_add_id = implode(',', $unique_product);
					}
				}
				// similar product ni main product ne highlights karva mate nu che end
				// get all productids and name from product table
				$add_id = '';
				if(isset($product_data['abpd_id']) && $product_data['abpd_id'] != ''){
					$add_id = ' AND abpd_id NOT IN ('.$product_data['abpd_id'].$similar_add_id.' )';
				}else{
					$add_id = ' AND abpd_id NOT IN ('.$similar_add_id.' )';
				}
				$activeProdsQry = "SELECT abpd_id, abpd_name, abpd_code FROM ab_product WHERE seller_sale_status = 'Y' AND abpd_active IN ('Y','N','D') ".$add_id." ORDER BY abpd_id DESC";
				$activeProdsQry = $pdo->_prepare($activeProdsQry);
				$activeProds = $pdo->_resultset();
				$json_similar_prod = json_encode($recmdProdsRe);
				?>
				<input form="prdct_frm" type="hidden" name="similar_product_json" value='<?php if(!empty($recmdProdsRe)) { echo $json_similar_prod; } ?>' >
	            <div class="widgetbox">
					<div class="headtitle">
						<h4 class="widgettitle">Similar Product</h4>
					</div>
					<div id="similar_prod" class="widgetcontent">
						<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
							<table class="table table-bordered responsive table-striped" id="similar_prod_table">
								<colgroup>
									<col class="con0" style="width:10%;">
									<col class="con1" style="width:90%;">
								</colgroup>
								<thead>
									<th>Select</th>
									<th>Product Name</th>
								</thead>
								<tbody>
									<?php 
									foreach ($activeProds as $rec_prod) {
										$checked = (isset($recmdProdsRe) && in_array($rec_prod['abpd_id'],$recmdProdsRe) ) ? 'checked' : '';
										?>
										<tr id="similar_prod_<?php echo $rec_prod['abpd_id']; ?>"> 
											<td>
												<input form="prdct_frm" type="checkbox" name="abpd_similar_prod[]" class="abpd_similar_prod" value="<?php echo $rec_prod['abpd_id']; ?>" <?php echo $checked; ?>>
											</td>
											<td><?php echo $rec_prod['abpd_name']; ?></td>
											<input type="hidden" class="code" value="<?php echo $rec_prod['abpd_code']; ?>">
										</tr>
										<?php 
									} ?>
								</tbody>
							</table>
						</div>
						</br>
						<div id="similarProd"></div>
					</div>
				</div>
	         <!-- Similar Product END -->
			<!-- More Same Product START -->
	            <?php
				$moreProdslistRe = array();
              	$json_same_prod = '';
              	$more_add_id = '';
              	if($abpd_id > 0)
                {
                	if(isset($product_data['abpd_id']) && $product_data['abpd_id'] != ''){
						$moreProdQryRe = "SELECT GROUP_CONCAT(abpsp_same_product_id) AS total_prod FROM `ab_product_same_products` WHERE abpsp_product_id=".$abpd_id." AND abpsp_same_product_id NOT IN (".$abpd_id.")";
						$moreProdQryRe = $pdo->_prepare($moreProdQryRe);
						$moreProdsReRecord = $pdo->_resultset();
						$moreProdslistRe = explode(',',$moreProdsReRecord[0]['total_prod']);
                		$json_same_prod = json_encode($moreProdslistRe);
                		$more_add_id = ' AND abpd_id NOT IN ('.$product_data['abpd_id'].')';
					}else{
                		$more_add_id = ' AND abpd_id NOT IN ('.$abpd_id.' )';
                	}
                }
				$moreActiveProdsQry = "SELECT abpd_id, abpd_name, abpd_code FROM ab_product WHERE seller_sale_status = 'Y' AND abpd_active IN ('Y','N','D') ".$more_add_id." ORDER BY abpd_id DESC";
				$moreActiveProdsQry = $pdo->_prepare($moreActiveProdsQry);
				$moreActiveProds = $pdo->_resultset();
				
				?>
				<input form="prdct_frm" type="hidden" name="same_product_json" value='<?php if(!empty($moreProdslistRe)) { echo $json_same_prod; } ?>' >
	            <div class="widgetbox">
					<div class="headtitle">
						<h4 class="widgettitle">More Same Product</h4>
					</div>
					<div id="same_prod" class="widgetcontent">
						<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
							<table class="table table-bordered responsive table-striped" id="same_prod_table">
								<colgroup>
									<col class="con0" style="width:10%;">
									<col class="con1" style="width:90%;">
								</colgroup>
								<thead>
									<th>Select</th>
									<th>Product Name</th>
								</thead>
								<tbody>
									<?php 
									foreach ($moreActiveProds as $rec_prod) {
                                    	$checkedmore = (isset($moreProdslistRe) && in_array($rec_prod['abpd_id'],$moreProdslistRe) ) ? 'checked' : '';
										?>
										<tr id="same_prod_<?php echo $rec_prod['abpd_id']; ?>"> 
											<td>
												<input form="prdct_frm" type="checkbox" name="abpd_same_prod[]" class="abpd_same_prod" value="<?php echo $rec_prod['abpd_id']; ?>" <?php echo $checkedmore; ?>>
											</td>
											<td><?php echo $rec_prod['abpd_name']; ?></td>
											<input type="hidden" class="code" value="<?php echo $rec_prod['abpd_code']; ?>">
										</tr>
										<?php 
									} ?>
								</tbody>
							</table>
						</div>
						</br>
						<div id="sameProd"></div>
					</div>
				</div>
	         <!-- More Same Product END -->
	         <!-- Competitor Rate Compare START -->
	         <div class="widgetbox">
					<div class="headtitle">
						<h4 class="widgettitle">Competitor Rate Compare</h4>
					</div>
					<div id="comp_rate" class="widgetcontent">
						<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
							<form method="post">
								<input type="hidden" name="product_id" value="<?php echo $abpd_id; ?>">
								<table  class="table responsive">
									<tbody>
										<tr>
								        	<td>
								        		<label>Date</label>
												<input type="text" name="abpc_date" id="abpc_date" value="" class="input-large" style="width:150px;">
								        	</td>
									      <td>
									        	<label>Variation</label>
												<select name="abpc_variation_id" class="select-large" style="width:162px;">
			   									<option value="">Select Variation</option>
													<?php if($abpd_id > 0){ $svdquery = "SELECT abpdv_id,abpdv_unit,abpdv_unit_quantity, abpdv_notes FROM ab_product_variation WHERE abpdv_status ='Y' AND abpdv_abpd_id=".$abpd_id;
													$svdresult = mysqlQuery($svdquery) or die(mysqlError());
													while ($v_res = mysqlFetchArray($svdresult)) { ?>
													 	<option value="<?php echo $v_res['abpdv_id']; ?>"><?php echo $v_res['abpdv_unit_quantity'].' '.$v_res['abpdv_unit']." (".$v_res['abpdv_notes'].")" ?></option>
													<?php } } ?>
												</select>
									      </td>
									   </tr>
								      <tr>
								      	<td>
								      		<label>Bighaat Rate</label>
												<input type="number" name="abpc_bighaat_rate" value="" class="input-large " style="width:150px;">
								      	</td>
								      	<td>
								      		<label>Amazon Rate</label>
												<input type="number" name="abpc_amazone_rate" value="" class="input-large " style="width:150px;">
								      	</td>
								      </tr>
								      <tr>
								      	<td colspan="2"><input type="submit" name="submit_rate_compare" class="btn btn-primary btn-large" value="Add Rate"></td>
								      </tr>
								    </tbody>
								</table>
							</form>
							<table class="table table-bordered responsive table-striped" id="comp_rate_table">
								<thead>
									<th>Date</th>
									<th>Variation</th>
									<th>Bighaat Rate</th>
									<th>Amazon Rate</th>
								</thead>
								<tbody>
									<?php 
									$sel_rate = mysqlQuery("SELECT a.*,v.abpdv_unit_quantity,v.abpdv_unit, v.abpdv_notes FROM ab_product_competitor_rate a JOIN ab_product_variation v ON a.abpc_variation_id = v.abpdv_id WHERE a.abpc_product_id = '".$abpd_id."' ORDER BY a.abpc_date DESC");
									while($sel_rate_res = mysqlFetchArray($sel_rate)){ ?>
										<tr>
											<td><?php echo date('d-m-Y',strtotime($sel_rate_res['abpc_date'])); ?></td>
											<td><?php echo $sel_rate_res['abpdv_unit_quantity'].' '.$sel_rate_res['abpdv_unit']." (".$sel_rate_res['abpdv_notes'].")"; ?></td>
											<td><?php echo $sel_rate_res['abpc_bighaat_rate']; ?></td>
											<td><?php echo $sel_rate_res['abpc_amazone_rate']; ?></td>
										</tr>
									<?php }
									/*foreach ($activeProds as $rec_prod) {
										$checked = (in_array($rec_prod['abpd_id'],$recmdProdsRe) ) ? 'checked' : '';
										?>
										<tr id="comp_rate_<?php echo $rec_prod['abpd_id']; ?>"> 
											<td>
												<input form="prdct_frm" type="checkbox" name="abpd_similar_prod[]" class="abpd_similar_prod" value="<?php echo $rec_prod['abpd_id']; ?>" <?php echo $checked; ?>>
											</td>
											<td><?php echo $rec_prod['abpd_name']; ?></td>
											<input type="hidden" class="code" value="<?php echo $rec_prod['abpd_code']; ?>">
										</tr>
										<?php 
									}*/ ?>
								</tbody>
							</table>
						</div>
						</br>
						<div id="similarProd"></div>
					</div>
				</div>
	         <!-- Competitor Rate Compare END -->
	         <?php if(isset($_GET['abpd_id']) && $_GET['abpd_id']!='' && $_GET['abpd_id']!=0){ ?>
		         <div class="widgetbox">
						<div class="headtitle">
							<h4 class="widgettitle">State Campaign</h4>
						</div>
						<div id="state_compaign" class="widgetcontent">
							<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
								<form method="post" id="state_campaign_form">
									<input type="hidden" name="product_id" id="product_id" value="<?php echo $abpd_id; ?>">
									<table  class="table responsive">
										<tbody>
											<tr>
									        	<td>
									        		<label>State</label>
									        		<select name="abpd_state_id[]" id="abpd_state_id" class="input-medium" onChange="check_is_google();" multiple=''>
									        		<?php $sel_state = mysqlQuery("SELECT * FROM ab_states WHERE abs_active='Y' ORDER BY abs_name ASC "); 
									        		while($sel_state_res = mysqlFetchArray($sel_state)){ ?>
									        			<option value="<?php echo $sel_state_res['abs_id'] ?>"><?php echo $sel_state_res['abs_name'] ?></option>
									        		<?php } ?>
									        	</td>
										      <td>
										        	<label>Months</label>
													<select name="abpd_state_compaign_month[]" id="abpd_state_compaign_month" class="input-medium" multiple='' onchange="check_is_google();">
						                  		<?php	
						                				foreach ($season_months as $key => $value) { ?>
						                					<option <?php echo $selected; ?> value="<?php echo $key; ?>" ><?php echo $value; ?></option>
						                				<?php } ?>
						                    	</select>
										      </td>
										   </tr>
									      <?php if($isgoogleEnable!='Y' && $isgoogleEnable!='TS'){ ?>
										      <tr>
										      	<td colspan="2"><input type="button" name="submit_state_campaign" id="submit_state_campaign" class="btn btn-primary btn-large" value="Add"></td>
										      </tr>
										   <?php } ?>
									    </tbody>
									</table>
								</form>
								<table class="table table-bordered responsive table-striped">
									<thead>
										<th>Status</th>
										<th>State</th>
										<th>Month</th>
                                        <th>Action</th>
									</thead>
									<tbody id="state_compaign_body">
										<?php 
										$sel_state = mysqlQuery("SELECT c.*,s.abs_name FROM ab_product_state_campaign c JOIN ab_states s ON c.state_id = s.abs_id WHERE product_id = '".$abpd_id."' ORDER BY id ");
										while($sel_state_res = mysqlFetchArray($sel_state)){ ?>
											<tr id="statecompaign_<?php echo $sel_state_res['id']; ?>">
												<td><input type="checkbox" name="state_compaign_check" class="state_compaign_check" data-id ="<?php echo $sel_state_res['id']; ?>" value="Y" <?php echo $sel_state_res['status']=='Y' ? 'checked' : ''; ?> ></td>
												<td><?php echo $sel_state_res['abs_name']; ?></td>
												<td><?php echo $sel_state_res['month_id']; ?></td>
                                                <td>
                                                	<a href="javascript:void(0);" onClick="ConfirmDelete('<?php echo $sel_state_res['id']; ?>')"><img title="Delete" alt="Delete" src="images/Delete - 16.png"></a>
                                                </td>
											</tr>
										<?php } ?>
									</tbody>
								</table>
							</div>
							</br>
							<div id="similarProd"></div>
						</div>
					</div>
					<div class="widgetbox">
						<div class="headtitle">
							<h4 class="widgettitle">Frequently bought together</h4>
						</div>
						<div id="freq_bought" class="widgetcontent">
							<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
								<form method="post" id="freq_bought_form">
									<input type="hidden" name="product_id" id="product_id" value="<?php echo $abpd_id; ?>">
									<table  class="table responsive">
										<tbody>
											<tr>
									        	<td>
									        		<label>Variation</label>
									        		<select name="abpd_freq_variation" id="abpd_freq_variation" class="input-medium" onChange="get_var_discount(this.value);">
									        			<option value="">Select Variation</option>
									        		<?php $sel_freq = mysqlQuery("SELECT abpdv_id,abpdv_unit_quantity,abpdv_unit,abpdv_notes FROM ab_product_variation WHERE abpdv_status='Y' AND abpdv_abpd_id = '".$abpd_id."' ORDER BY abpdv_display_order ASC "); 
									        		while($sel_freq_res = mysqlFetchArray($sel_freq)){
									        			if($sel_freq_res['abpdv_notes']!=''){
									        				$notes = ' ('.$sel_freq_res['abpdv_notes'].')';
									        			}else{
									        				$notes = '';
									        			}
									        				 ?>
									        			<option value="<?php echo $sel_freq_res['abpdv_id'] ?>"><?php echo $sel_freq_res['abpdv_unit_quantity'].' '.$sel_freq_res['abpdv_unit'].$notes; ?></option>
									        		<?php } ?>
									        		</select>
									        	</td>
									      </tr>
									      <tr>
									      	<td>
									      		<label>Product</label>
									      		<input type="hidden" id="freq_bought_product_id">
									      		<input type="hidden" id="freq_bought_product_name">
									      		<input type="text" name="" value="" class="serach-name-pro typeahead input-medium" placeholder="Search Product & Product Code" id="search_product" onChange="get_prod_var();">
									      	</td>
									      	<td>
									      		<label>Product Variation</label>
									      		<select id="abpd_sel_freq_variation" class="input-medium" >
									        			<option value="">Select Variation</option>
									        		</select>
									      	</td>
									      	<td>
									      		<label>Discount</label>
									      		<input type="text" value="" class="input-medium numer_only" placeholder="Enter Discount Value" id="freq_var_discount_val">
									      	</td>
									      	<td><input type="button" id="add_freq_bought" class="btn btn-primary" value="+"></td>
									      </tr>
									      <tr>
									      	<td colspan="4">Product List</td>
									      </tr>
									      <tr>
									      	<table class="table responsive">
									      		<thead>
									      			<tr>
									      				<th>Product</th>
									      				<th>Product Variation</th>
									      				<th>Discount Value</th>
									      				<th></th>
									      			</tr>
									      		</thead>
									      		<input type="hidden" name="freq_cnt" id="freq_cnt" value="0">
									      		<tbody id="tbody_freq_bought">
									      			<tr><td colspan="4">No Data Found</td></tr>
									      		</tbody>
									      	</table>
										   </tr>
									      <tr>
									      	<td colspan="2"><input type="button" name="submit_freq_bought" id="submit_freq_bought" class="btn btn-primary btn-large" value="Submit" style="display: none;"></td>
									      </tr>
									    </tbody>
									</table>
								</form>
								<table class="table table-bordered responsive table-striped">
									<thead>
										<th>Variation</th>
										<th>Product</th>
										<th>Product Variation</th>
										<th>Discount</th>
										<th></th>
									</thead>
									<tbody id="freq_bought_tbody">
										<tr><td colspan="4">No Data Found</td></tr>
									</tbody>
								</table>
							</div>
							</br>
							<div id="similarProd"></div>
						</div>
					</div>
				<?php } ?>
				<!-- SELLER PRODUCT START -->
	         <div class="widgetbox">
					<div class="headtitle">
						<h4 class="widgettitle">Seller Product</h4>
					</div>
					<div id="comp_rate" class="widgetcontent">
						<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
							<table class="table table-bordered responsive table-striped" id="comp_rate_table">
								<thead>
									<th>Seller Name</th>
									<th>Variation</th>
									<th>Sell Price</th>
								</thead>
								<tbody>
									<?php 
									$sel_rate = mysqlQuery("SELECT a.*,v.abpdv_unit_quantity,v.abpdv_unit,abs.absl_name,v.abpdv_notes 
													FROM ab_multiseller_product a 
													JOIN ab_product_variation v ON a.variation_id = v.abpdv_id 
													JOIN ab_seller abs ON a.seller_id = abs.absl_id
													WHERE a.product_id = '".$abpd_id."' ORDER BY a.id DESC");
									while($sel_rate_res = mysqlFetchArray($sel_rate)){ 
										if($sel_rate_res['abpdv_notes']!=''){
											$notes = ' ('.$sel_rate_res['abpdv_notes'].')';
										}else{
											$notes = '';
										}
										?>
										<tr>
											<td><?php echo $sel_rate_res['absl_name']; ?></td>
											<td><?php echo $sel_rate_res['abpdv_unit_quantity'].' '.$sel_rate_res['abpdv_unit'].$notes; ?></td>
											<td><?php echo $sel_rate_res['sale_price']; ?></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
						</br>
						<div id="similarProd"></div>
					</div>
				</div>
	         <!-- SELLER PRODUCT END -->
	         <?php if(isset($_GET['abpd_id']) && $_GET['abpd_id']!='' && $_GET['abpd_id']!=0){ ?>
		         <!-- RESTRICTED STATE START -->
		         <div class="widgetbox">
						<div class="headtitle">
							<h4 class="widgettitle">Restricted State</h4>
						</div>
						<div class="widgetcontent">
							<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
								<form method="post" id="restricted_state">
									<input type="hidden" name="product_id" id="product_id" value="<?php echo $abpd_id; ?>">
									<table  class="table responsive">
										<tbody>
											<tr>
									        	<td>
										        	<label>State</label>
													<select name="abrs_state_id[]" id="abrs_state_id" class="select-large" style="width:162px;" multiple required>
														<?php $svdquery = "SELECT abs_id,abs_name FROM ab_states WHERE abs_active ='Y'";
														$svdresult = mysqlQuery($svdquery);
														while ($v_res = mysqlFetchArray($svdresult)) { ?>
														 	<option value="<?php echo $v_res['abs_id']; ?>"><?php echo $v_res['abs_name']; ?></option>
														<?php } ?>
													</select>
										      </td>
										      <td>
										      	<input type="submit" class="hidden" name="hidden_submit_restricted_state" id="hidden_submit_restricted_state">
										      	<input type="button" name="submit_restricted_state" id="submit_restricted_state" class="btn btn-primary btn-large" value="Add State">
										      </td>
									      </tr>
									   </tbody>
									</table>
								</form>
								<table class="table table-bordered responsive table-striped" id="rest_state_table">
									<thead>
										<th>State</th>
										<th>Action</th>
									</thead>
									<tbody id="rest_state_body">
										<?php 
										$sel_state = mysqlQuery("SELECT a.abrs_id,v.abs_name FROM ab_product_restricted_state a JOIN ab_states v ON a.abrs_state_id = v.abs_id WHERE a.abrs_product_id = '".$abpd_id."' ORDER BY a.abrs_created_at DESC");
										if(mysqlNumRow($sel_state) > 0){
											while($sel_state_res = mysqlFetchArray($sel_state)){ ?>
												<tr>
													<td><?php echo $sel_state_res['abs_name']; ?></td>
													<td>
														<input type="button" id="del_restricted_state" class="btn btn-primary" onClick="remove_rest_state('<?php echo $sel_state_res['abrs_id']; ?>','<?php echo $abpd_id; ?>')" value="x"></td>
												</tr>
											<?php } }else{ ?>
												<tr>
													<td colspan="2">No State Found</td>
												</tr>
											<?php } ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
		         <!-- RESTRICTED STATE END -->
		         <!-- STATEWISE PRODUCT DISCOUNT START -->
		         <div class="widgetbox">
						<div class="headtitle">
							<h4 class="widgettitle">State wise Product Discount</h4>
						</div>
						<div class="widgetcontent">
							<div class="recomadend_wrap" style="max-height:300px;overflow-y:scroll;">
								<form method="post" id="statewise_product_discount_form">
									<input type="hidden" name="product_id" id="product_id" value="<?php echo $abpd_id; ?>">
									<table  class="table responsive">
										<tbody>
											<tr>
												<td>
													<select name="absd_variation_id" id="absd_variation_id" class="input-medium">
										        		<option value="">Select Variation</option>
										        		<?php $sel_freq = mysqlQuery("SELECT abpdv_id,abpdv_unit_quantity,abpdv_unit,abpdv_notes FROM ab_product_variation WHERE abpdv_status='Y' AND abpdv_abpd_id = '".$abpd_id."' ORDER BY abpdv_display_order ASC "); 
										        		while($sel_freq_res = mysqlFetchArray($sel_freq)){
										        			if($sel_freq_res['abpdv_notes']!=''){
										        				$notes = ' ('.$sel_freq_res['abpdv_notes'].')';
										        			}else{
										        				$notes = '';
										        			}
										        				 ?>
										        			<option value="<?php echo $sel_freq_res['abpdv_id'] ?>"><?php echo $sel_freq_res['abpdv_unit_quantity'].' '.$sel_freq_res['abpdv_unit'].$notes; ?></option>
										        		<?php } ?>
										        	</select>
									        	</td>
									        	<td>
													<select name="absd_state_id[]" id="absd_state_id" class="select-large" style="width:162px;" multiple required>
														<?php $svdquery = "SELECT abs_id,abs_name FROM ab_states WHERE abs_active ='Y'";
														$svdresult = mysqlQuery($svdquery);
														while ($v_res = mysqlFetchArray($svdresult)) { ?>
														 	<option value="<?php echo $v_res['abs_id']; ?>"><?php echo $v_res['abs_name']; ?></option>
														<?php } ?>
													</select>
										      </td>
											</tr>
											<tr>
									        	
										      <td>
										      	<input type="text" value="" class="input-medium numer_only" placeholder="Enter Discount Value" name="statewise_discount_val" id="statewise_discount_val">
										      </td>
										      <td>
										      	<input type="submit" class="hidden" name="hidden_submit_statewise_product_discount" id="hidden_submit_statewise_product_discount">
										      	<input type="button" name="submit_statewise_product_discount" id="submit_statewise_product_discount" class="btn btn-primary btn-large" value="Add Discount">
										      </td>
									      </tr>
									   </tbody>
									</table>
								</form>
								<table class="table table-bordered responsive table-striped" id="rest_state_table">
									<thead>
										<th>Variation</th>
										<th>State</th>
										<th>Discount</th>
										<th>Action</th>
									</thead>
									<tbody id="state_wise_product_discount_body">
										<?php 
										$sel_state = mysqlQuery("SELECT a.absd_id,s.abs_name,a.absd_discount,v.abpdv_unit,v.abpdv_unit_quantity,v.abpdv_notes FROM ab_product_statewise_discount a JOIN ab_states s ON a.absd_state_id = s.abs_id JOIN ab_product_variation as v ON v.abpdv_id = a.absd_variation_id WHERE a.absd_product_id = '".$abpd_id."' ORDER BY a.absd_created_at DESC");
										if(mysqlNumRow($sel_state) > 0){
											while($sel_state_res = mysqlFetchArray($sel_state)){ ?>
												<tr>
													<?php if($sel_state_res['abpdv_notes']!=''){
									        				$notes = ' ('.$sel_state_res['abpdv_notes'].')';
									        			}else{
									        				$notes = '';
									        			} ?>
													<td><?php echo $sel_state_res['abpdv_unit_quantity'].' '.$sel_state_res['abpdv_unit'].$notes; ?></td>
													<td><?php echo $sel_state_res['abs_name']; ?></td>
													<td><?php echo $sel_state_res['absd_discount']; ?></td>
													<td>
														<input type="button" id="del_statewise_discount" class="btn btn-primary" onClick="remove_statewise_discount('<?php echo $sel_state_res['absd_id']; ?>','<?php echo $abpd_id; ?>')" value="x"></td>
												</tr>
											<?php } }else{ ?>
												<tr>
													<td colspan="4">No State Found</td>
												</tr>
											<?php } ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
		         <!-- STATEWISE PRODUCT DISCOUNT END -->
		      <?php } ?>
	          </div> <!-- END OF span4 -->
	        </div> <!-- END OF row-fluid -->
	        <?php include('Include/footer.php'); 
				CloseConnection($db);
			?>
	      </div> <!-- END OF maincontentinner -->
	    </div> <!-- END OF maincontent -->
	  </div> <!-- END OF rightpanel -->
	</div> <!-- END OF mainwrapper -->
	<div id="imageModal" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="width: auto;">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<h3 id="myModalLabel">Add/Edit Image</h3>
		</div> <!-- END OF modal-header -->
		<div class="modal-body " id="mymodalhtml">
			<?php if($_GET["abpd_id"] > 0 && $product_data['abpd_id'] > 0){ }else{ ?>
				<input type="file"  name="images" id="image_imgdigital"  style="display:none;" >
			<?php } ?>
	  </div>
	  <div class="modal-footer" style="text-align:center;">
	  	<button id="cancel_btn" aria-hidden="true" data-dismiss="modal" class="btn ">Cancel</button>
	  	<button class="btn btn-primary" onClick="subform();" id="save_imgdigital"><span class="icon-ok icon-white"></span> Save Changes</button>
	  </div> <!-- END OF modal-footer -->
	</div> <!-- END OF imageModal -->
	<script type="text/javascript">
		jQuery("#abpd_agribegri_fulfilment").on('click',function(){
			if(jQuery(this).is(':checked')){
				jQuery(".td_fulfilment_amount").show();
				jQuery(".abpdv_fulfilment_amount").prop('readonly', true);
				calculate_fulfilment_charge();
			}else{
				jQuery(".td_fulfilment_amount").hide();
				jQuery(".abpdv_fulfilment_amount").prop('readonly', true);
				jQuery(".abpdv_fulfilment_amount").val(0);
				jQuery(".single_variation_group_wrap").each(function(){
					var variation = jQuery(this); 
					calculate_sale_price(variation);
				});
			}
		});
		jQuery("#submit_restricted_state").on('click',function(e){
			e.preventDefault();
			var state = jQuery("#abrs_state_id").val();
			var product_id = jQuery("#product_id").val();
			if(state==null){
				jAlert('Please Select State!', 'Alert Dialog');
				return false;
			}else{
				jQuery("#hidden_submit_restricted_state").trigger('click');
			}
		});
		function remove_rest_state(rest_state_tbl_id,product_id){
			if(rest_state_tbl_id!=''){
				jConfirm('Are you sure you want to delete this state?','Confirm Dialog',function(r){
					if(r){
						jQuery.ajax({
							url:'add_prod_variation.php',
							data:{rest_state_tbl_id:rest_state_tbl_id,product_id:product_id,type:'del_rest_state'},
							type:'POST',
							dataType: 'json',
							success:function(response){
								jQuery("#rest_state_body").empty();
								jQuery("#rest_state_body").append(response.html);	
							}	
						});
					}
				});
			}
		}
		function get_var_discount(variation_id){
			var product_id = jQuery("#product_id").val();
			if(variation_id!='' && product_id!=''){
				jQuery.ajax({
					url:'add_prod_variation.php',
					data:{product_id:product_id,variation_id:variation_id,type:'get_freq_bought'},
					type:'POST',
					dataType: 'json',
					success:function(response){
						jQuery("#search_product").val('');
						jQuery("#freq_bought_product_id").val('');
						jQuery("#abpd_sel_freq_variation").val('');
						jQuery("#freq_var_discount_val").val('');
						jQuery("#tbody_freq_bought").empty();
						jQuery("#tbody_freq_bought").append('<tr><td colspan="4">No Product Found</td></tr>');
						jQuery("#submit_freq_bought").hide();
						jQuery("#freq_bought_tbody").empty();
						jQuery("#freq_bought_tbody").append(response.html);	
					}	
				});
			}
		}
		function remove_dis_product(freq_tbl_id){
			if(freq_tbl_id!=''){
				jConfirm('Are you sure you want to delete this product?','Confirm Dialog',function(r){
					if(r){
						jQuery.ajax({
							url:'add_prod_variation.php',
							data:{freq_tbl_id:freq_tbl_id,type:'upd_freq_bought'},
							type:'POST',
							dataType: 'json',
							success:function(response){
								jQuery("#search_product").val('');
								jQuery("#freq_bought_product_id").val('');
								jQuery("#abpd_sel_freq_variation").val('');
								jQuery("#freq_var_discount_val").val('');
								jQuery("#tbody_freq_bought").empty();
								jQuery("#tbody_freq_bought").append('<tr><td colspan="4">No Product Found</td></tr>');
								jQuery("#submit_freq_bought").hide();
								jQuery("#freq_bought_tbody").empty();
								jQuery("#freq_bought_tbody").append(response.html);	
							}	
						});
					}
				});
			}
		}
		jQuery("#submit_statewise_product_discount").on('click',function(e){
			e.preventDefault();
			var state = jQuery("#absd_state_id").val();
			var product_id = jQuery("#product_id").val();
			var variation_id = jQuery("#absd_variation_id").val();
			var discount = jQuery("#statewise_discount_val").val();
			if(variation_id==''){
				jAlert('Please Select Variation!', 'Alert Dialog');
				return false;
			}else if(state==null){
				jAlert('Please Select State!', 'Alert Dialog');
				return false;
			}else if(discount==''){
				jAlert('Please Enter Discount Percentage!', 'Alert Dialog');
				return false;
			}else{
				jQuery.ajax({
					url:'add_prod_variation.php',
					data:{product_id:product_id,state:state,variation_id:variation_id,discount:discount,type:'add_statewise_product_discount'},
					type:'POST',
					dataType: 'json',
					success:function(response){
						jQuery("#state_wise_product_discount_body").empty();
						jQuery("#state_wise_product_discount_body").append(response.html);
						jQuery("#absd_state_id").val('');
						jQuery("#absd_state_id").multiselect('destroy');
						jQuery('#absd_state_id').multiselect({ 
							includeSelectAllOption: true,
							enableFiltering: true,
							enableCaseInsensitiveFiltering: true,
							maxHeight: 170 ,
							nonSelectedText:'Select State',
							numberDisplayed: 0,       
						});
						jQuery("#absd_variation_id").val('');
						jQuery("#statewise_discount_val").val('');
					}	
				});
			}
		});
		function remove_statewise_discount(state_dis_tbl_id,product_id){
			if(state_dis_tbl_id!=''){
				jConfirm('Are you sure you want to delete this state?','Confirm Dialog',function(r){
					if(r){
						jQuery.ajax({
							url:'add_prod_variation.php',
							data:{state_dis_tbl_id:state_dis_tbl_id,product_id:product_id,type:'del_state_discount'},
							type:'POST',
							dataType: 'json',
							success:function(response){
								jQuery("#state_wise_product_discount_body").empty();
								jQuery("#state_wise_product_discount_body").append(response.html);	
							}	
						});
					}
				});
			}
		}
		jQuery('#search_product').on('keypress',function(e){
			if(e.keyCode==13){
				var serval = jQuery('#search_product').val();
				var byval = jQuery('#search_by').val();
				//window.location.href='products.php?serval='+serval+'&byval='+byval;
				if(serval!=""){	
					/*jQuery("#paging_id").val('1');
					jQuery("#serval").val(serval);
					jQuery("#sub_serval").val('');
					jQuery("#byval").val(byval);
					jQuery("#header_search_frm").trigger("submit");*/
				}else{
					jAlert("Please type atleast 5 characters");
					return false;
				}
			}
		});
		var headerWidth = parseInt(jQuery("#search_product").outerWidth()) - 20;
		jQuery("#search_product").autocomplete({
	      open: function() { 
          	// After menu has been opened, set width to 100px
	          jQuery('.ui-menu')
	              .width(headerWidth);
	      } ,
		  source: function( request, response ) {
		  		var prod_id = '<?php echo $_GET['abpd_id']; ?>';
		  		if(prod_id!=''){
		        jQuery.ajax( {
		          url: "typehead_search.php",
				  type: 'post',
		          dataType: "json",
		          data: {
		            query: request.term,prod_id:prod_id
		          },
		          success: function( data ) {
		            response( data );
		          }
		        } );
		     }
	      },
	      //minLength: 4,
	      select: function( event, ui ) {
	         //console.log( "Selected: " + ui.item.id);
	         jQuery("#freq_bought_product_id").val(ui.item.id);
	         jQuery("#freq_bought_product_name").val(ui.item.value);
	         jQuery("#search_product").val(ui.item.id);
			 //window.location.href=ui.item.value;
			 //return false;
	      }
	   } );
		function get_prod_var(){
			var prod_id = jQuery("#freq_bought_product_id").val();
			if(prod_id>0){
				jQuery.ajax({
					url:'add_prod_variation.php',
					type:'POST',
					async:false,
					dataType: "json",
					data:{prod_id:prod_id,type:'get_prod_variation'},
					success:function(data){
						//console.log(data.html);
						jQuery("#abpd_sel_freq_variation").html(data.html);
						//jQuery('#order_remark').html(data);
						//jQuery('#order_remark').val(data);
						/*jQuery('#display_remark').html(data.display_val);
						jQuery('#old_remark').val(data.input_val);*/
					}
				});
			}
		}
		jQuery("#add_freq_bought").on('click',function(){
			var main_variation_id = jQuery("#abpd_freq_variation").val();
			var product_id = jQuery("#freq_bought_product_id").val();
			var product_name = jQuery("#freq_bought_product_name").val();
			var variation_id = jQuery("#abpd_sel_freq_variation").val();
			var variation_name = jQuery("#abpd_sel_freq_variation").find('option:selected').text();
			var discount_val = jQuery("#freq_var_discount_val").val();
			if(main_variation_id==''){
				jAlert("Please Select Variation");
				return false;
			}else if(product_id==''){
				jAlert("Please Select Product");
				return false;
			}else if(variation_id==''){
				jAlert("Please Select Product Variation");
				return false;
			}else if(discount_val==''){
				jAlert("Please Enter Discount");
				return false;
			}else{
				var freq_cnt = jQuery("#freq_cnt").val();
				if(freq_cnt==0){
					jQuery("#tbody_freq_bought").empty();
				}
				var i = parseInt(freq_cnt) + 1;
				jQuery("#freq_cnt").val(i);
				var html='';
				html+='<tr id="tr_freq_bought_'+i+'">'+      				
					'<input type="hidden" name="freq_var_product_id[]" value="'+product_id+'">'+
					'<input type="hidden" name="freq_var_product_name[]" value="'+product_name+'">'+
					'<input type="hidden" name="freq_bought_product_variation_id[]" value="'+variation_id+'">'+
					'<input type="hidden" name="freq_bought_product_variation_name[]" value="'+variation_name+'">'+
					'<input type="hidden" name="freq_var_discount_val[]" value="'+discount_val+'">'+
					'<td>'+product_name+'</td>'+
			   	'<td>'+variation_name+'</td>'+
			   	'<td>'+discount_val+'</td>'+
			   	'<td><input type="button" class="btn btn-primary" value="x" onclick="del_freq_bought('+i+')"></td>'+
				'</tr>';
						/*'<tr id="tr_freq_bought_'+i+'">'+
	   				'<td>'+
				        	'<input type="hidden" name="freq_bought_product_id[]" id="freq_bought_product_id_'+i+'">'+
				        	'<input type="text" name="" value="" class="serach-name-pro typeahead input-medium" placeholder="Search Product & Product Code" id="search_product_'+i+'" onchange="get_prod_var();">'+
				      '</td>'+
				   	'<td>'+
				   		'<select name="abpd_sel_freq_variation[]" id="abpd_sel_freq_variation_'+i+'" class="input-medium" >'+
			        			'<option value="">Select Variation</option>'+
			        		'</select>'+
				   	'</td>'+
				   	'<td>'+
				   		'<input type="text" name="freq_var_discount_val[]" value="" class="input-medium numer_only" placeholder="Enter Discount Value" id="freq_var_discount_val_'+i+'" >'+
				   	'</td>'+
				   	'<td><input type="button" id="del_freq_bought_'+i+'" class="btn btn-primary" value="x" onclick="del_freq_bought('+i+')"></td>'+
	   			'</tr>';*/
	   		jQuery("#tbody_freq_bought").append(html);
	   		jQuery("#submit_freq_bought").show();
	   		jQuery("#search_product").val('');
	 			jQuery("#freq_bought_product_id").val('');
				jQuery("#freq_bought_product_name").val('');
				jQuery("#abpd_sel_freq_variation").val('');
				jQuery("#freq_var_discount_val").val('');
			}
		});
		function del_freq_bought(cnt){
			jConfirm('Are you sure you want to delete this product?','Confirm Dialog',function(r){
				if(r){
					jQuery("#tr_freq_bought_"+cnt).remove();
				}
			});
		}
		jQuery("#submit_freq_bought").on('click',function(e){
			var product_id = jQuery("#product_id").val();
			var variation_id = jQuery("#abpd_freq_variation").val();
			var freq_bought_product_id = [];
			var freq_bought_variation_id = [];
			var discount_val = [];
			jQuery("input[name='freq_var_product_id[]']").each(function(){
			   freq_bought_product_id.push(jQuery(this).val());
			});
			jQuery("input[name='freq_bought_product_variation_id[]']").each(function(){
			   freq_bought_variation_id.push(jQuery(this).val());
			});
			jQuery("input[name='freq_var_discount_val[]']").each(function(){
			   discount_val.push(jQuery(this).val());
			});
			/*var freq_bought_product_id = jQuery("input[name='freq_var_product_id[]']").val();
			var freq_bought_variation_id = jQuery("input[name='freq_bought_product_variation_id[]']").val();
			var discount_val = jQuery("input[name='freq_var_discount_val[]']").val();*/
			//console.log(product_id+'--'+variation_id+'--'+freq_bought_product_id+'--'+freq_bought_variation_id+'--'+discount_val);
			if(variation_id==''){
				jAlert('Please Select Variation!', 'Alert Dialog');
				return false;
			}else if(freq_bought_product_id.length === 0){
				jAlert('Please Select Product!', 'Alert Dialog');
				return false;
			}else if(freq_bought_variation_id.length === 0){
				jAlert('Please Select Product Variation!', 'Alert Dialog');
				return false;
			}else if(discount_val.length === 0){
				jAlert('Please Enter Discount!', 'Alert Dialog');
				return false;
			}else{
				jQuery.ajax({
					url:'add_prod_variation.php',
					data:{product_id:product_id,variation_id:variation_id,freq_bought_product_id:freq_bought_product_id,freq_bought_variation_id:freq_bought_variation_id,discount_val:discount_val,type:'add_freq_bought'},
					type:'POST',
					dataType: 'json',
					success:function(response){
						jQuery("#search_product").val('');
						jQuery("#freq_bought_product_id").val('');
						jQuery("#abpd_sel_freq_variation").val('');
						jQuery("#freq_var_discount_val").val('');
						jQuery("#tbody_freq_bought").empty();
						jQuery("#tbody_freq_bought").append('<tr><td colspan="4">No Product Found</td></tr>');
						jQuery("#submit_freq_bought").hide();
						jQuery("#freq_bought_tbody").empty();
						jQuery("#freq_bought_tbody").append(response.html);	
					}	
				});
			}
			/*if(variation_id==''){
				jAlert('Please Select Variation!', 'Alert Dialog');
				return false;
			}else if(freq_bought_product_id==''){
				jAlert('Please Select Product!', 'Alert Dialog');
				return false;
			}else if(abpd_sel_freq_variation==''){
				jAlert('Please Select Product Variation!', 'Alert Dialog');
				return false;
			}else if(discount_val=='' || discount_val==0){
				jAlert('Please Enter Discount!', 'Alert Dialog');
				return false;
			}else{
				jQuery.ajax({
					url:'add_prod_variation.php',
					data:{product_id:product_id,variation_id:variation_id,freq_bought_product_id:freq_bought_product_id,abpd_sel_freq_variation:abpd_sel_freq_variation,discount_val:discount_val,type:'add_freq_bought'},
					type:'POST',
					dataType: 'json',
					success:function(response){
						jQuery("#search_product").val('');
						jQuery("#freq_bought_product_id").val('');
						jQuery("#abpd_sel_freq_variation").val('');
						jQuery("#freq_var_discount_val").val('');
					}	
				});
			}*/
		});
		jQuery('body').on('change', ".abpd_unit", function(){
			var unit_name = jQuery(this).val();
			var variationdiv = jQuery(this).parents('.single_variation_group_wrap');
			if(unit_name!=''){
				jQuery.ajax({
					url:'add_prod_variation.php',
					type:'POST',
					async:false,
					dataType: "json",
					data:{unit_name:unit_name,type:'update_notes_val'},
					success:function(data){
						if(data.found == 1){
							variationdiv.find('.drp_sel').show();
							variationdiv.find('.note_txt').hide();
							variationdiv.find('.abpdv_notes').empty();
							variationdiv.find('.abpdv_notes').append(data.html);
							variationdiv.find('.abpdv_notes').prop('required',true);
							variationdiv.find('.abpdv_notes_txt').prop('required',false);
						}else{
							variationdiv.find('.drp_sel').hide();
							variationdiv.find('.note_txt').show();
							variationdiv.find('.abpdv_notes_txt').val('');
							variationdiv.find('.abpdv_notes').prop('required',false);
							variationdiv.find('.abpdv_notes_txt').prop('required',false);
						}
					}
				});
			}else{
				variationdiv.find('.drp_sel').hide();
				variationdiv.find('.note_txt').show();
				variationdiv.find('.abpdv_notes_txt').val('');
				variationdiv.find('.abpdv_notes_txt').prop('required',false);
				variationdiv.find('.abpdv_notes').empty();
				variationdiv.find('.abpdv_notes').append('<option value="">Select Notes</option>');
				variationdiv.find('.abpdv_notes').prop('required',false);
			}
		});
		jQuery('#abpd_google_enable').on('change',function(){
			if(jQuery(this).val() == 'Y' || jQuery(this).val() == 'TS'){
				jQuery("#submit_state_campaign").hide();
			}else{
				jQuery("#submit_state_campaign").show();
			}
		});
		function	check_is_google(){
			var state_id = jQuery("#abpd_state_id").val();
			var month_id = jQuery("#abpd_state_compaign_month").val();
			if(state_id!=null || month_id!=null){
				jQuery("#abpd_google_enable option[value='Y']").attr("disabled","disabled");
				jQuery("#abpd_google_enable option[value='TS']").attr("disabled","disabled");
				//jQuery("#abpd_google_enable")
			}else{
				jQuery("#abpd_google_enable option[value='Y']").removeAttr("disabled","disabled");
				jQuery("#abpd_google_enable option[value='TS']").removeAttr("disabled","disabled");
			}
		}
		jQuery('#abpc_date').datepicker({
			changeMonth:true,
			changeYear:true,
			dateFormat:'yy-mm-dd',
			minDate: 0,
		});
		jQuery(".state_compaign_check").on('click',function(){
			if (jQuery(this).prop('checked')==true){ 
				var status = 'Y';
			}else{
				var status = 'N';
			}
			var id = jQuery(this).data('id');
			jQuery.ajax({
				url:'add_prod_variation.php',
				type:'POST',
				async:false,
				dataType: "json",
				data:{id:id,status:status,type:'update_state_status'},
				success:function(data){
				}
			});
		});
		function	add_variation_val(){
			var cnt = parseInt(jQuery('#counter').val());
			cnt = cnt + 1;
			jQuery('#counter').val(cnt);
			var html = '';
			html+='<tr id="variation_row'+cnt+'">'+
         			'<td>'+
         				'<select name="variation_title[]" id="variation_title'+cnt+'" class="select-large variation_title" onchange="title_variation(this.value,'+cnt+');">'+
               			'<option value="">Select Title</option>'+
               			<?php foreach ($product_variation as $key => $value) { ?>
               				'<option value="<?php echo $key; ?>"><?php echo $value; ?></option>'+
               			<?php } ?>
               		'</select>'+
               	'</td>'+
               	'<td>'+
               		'<input type="text" name="variation_val[]" id="variation_val'+cnt+'" value="" onchange="get_color_val();" class="input-large variation_val">'+
               	'</td>'+
               	'<td>'+
               		'<input type="file" name="variation_file[]" id="variation_file'+cnt+'" value="" class="input-large" style="display:none;"><input type="hidden" name="old_image_id[]" id="old_image_id'+cnt+'" value="0">'+
               	'</td>'+
               	'<td>'+
               		'<a href="javascript:void(0);" onclick="remove_variation('+cnt+',0);"><i class="fa fa-trash"></i></a>'+
               	'</td>'+
         		'</tr>';
         jQuery("#product_variation").append(html);
		}
		function remove_variation(cnt,row_id){
			if(row_id!=0){
				jConfirm('Are you sure you want to delete this Variation Unit?','Confirm Dialog',function(r){
					if(r){
						jQuery.ajax({
							url:"add_prod_variation.php",
							data: {'row_id':row_id,'action':'delete_variation_unit' },
							type:"POST",
							success:function(result){
								jAlert('Unit Deleted Successfully','Alert Dialog');
								jQuery("#variation_row"+cnt).remove();
							}			
						});
					}
			 	});
			}else{
				jQuery("#variation_row"+cnt).remove();
			}
		}
		function title_variation(title_id,cnt){
			if(title_id==1){
				jQuery("#variation_file"+cnt).show();
			}else{
				jQuery("#variation_file"+cnt).hide();
			}
			get_color_val();
		}
		function get_color_val(){
			var output = [];
			jQuery('select[name="variation_title[]"]').each(function(){
				var html = '';
				var select_val = jQuery(this).closest('tr').find('td').find('select').val();
				if(select_val==1){
					var color_val = jQuery(this).closest('tr').find('td').find('.variation_val').val();
					if(color_val!=''){
						html+='<option value="'+color_val+'">'+color_val+'</option>';
						output.push(html);
					}
				}  
        	});
        	jQuery('.abpdv_variation_color').empty();
			jQuery(".abpdv_variation_color").multiselect('destroy').removeData();
			jQuery(".abpdv_variation_color").append(output);
			jQuery(".abpdv_variation_color").multiselect('refresh');
			jQuery(jQuery(".abpdv_variation_color").closest('td').find('p').nextAll()).each(function() {
				jQuery(this).remove();
			});
		}
		jQuery("#abpd_having_color").click(function() {
		   if(jQuery(this).is(":checked")) {
		      jQuery("#color_p").show();
		   } else {
		      jQuery("#color_p").hide();
		      jQuery("#abpd_product_color").val('');
		   }
		});
		jQuery("#abpd_product_color").tagsInput({'height':'100px','width':'60%'});
		var xhr_p = null;	
		function Check_name(val){
			if(xhr_p!=null){
				xhr_p.abort();
				xhr_p  = null;
			}
			if(val!=''){
				xhr_p = jQuery.ajax({
					url:'add_product.php',
					type:'POST',
					data:{p_name:val,p_id:jQuery('#abpd_id').val(),'type':'name_check'},
					success:function(data){
						if(data=='exist'){
							jQuery('#pnmae_exist').val('Yes');
						}else{
							jQuery('#pnmae_exist').val('No');
						}
					}
				});
			}	
		}
		function deleteImg(abpd_id,abpi_id,type){
			jConfirm('Are you sure you want to delete this Image?','Confirm Dialog',function(r){
				if(r){
					jQuery.ajax({
						url:"productimages.php?abpd_id="+abpd_id+"&abpi_id="+abpi_id+"&type="+type+"&action=delete",
						type:"GET",
						success:function(result){
							jAlert('Image Deleted Successfully','Alert Dialog');
							jQuery('#image_con').html('');
							jQuery('#image_con').html(result);
						}			
					});
				}
		 	});
		}
		function deleteGuideline(abpd_id){
			jConfirm('Are you sure to delete this document?','Confirm Dialog',function(r){
				if(r){
					jQuery.ajax({
						url:"add_prod_variation.php",
						type:"POST",
						data:{abpd_id:abpd_id,type:'delete_guideline'},
						success:function(data){
							if(data=="success"){
								jAlert('Guideline document deleted successfully','Alert Dialog');
							}else{
								jAlert('No document found!','Alert Dialog');
							}
							location.reload();
						}			
					});
				}
		 	});
		}
		function deleteFacebookImg(abpd_id){
			jConfirm('Are you sure to delete this image?','Confirm Dialog',function(r){
				if(r){
					jQuery.ajax({
						url:"add_prod_variation.php",
						type:"POST",
						data:{abpd_id:abpd_id,type:'delete_facebook_feed_img'},
						success:function(data){
							if(data=="success"){
								jAlert('Facebook Feed Image deleted successfully','Alert Dialog',function(){
									location.reload();
								});
							}else{
								jAlert('No image found','Alert Dialog',function(){
									location.reload();
								});
							}
						}			
					});
				}
		 	});
		}
        function deleteAplusImg(abpd_id){
			jConfirm('Are you sure to delete A+ image?','Confirm Dialog',function(r){
				if(r){
					jQuery.ajax({
						url:"add_prod_variation.php",
						type:"POST",
						data:{abpd_id:abpd_id,type:'delete_aplus_img'},
						success:function(data){
							if(data=="success"){
								jAlert('A+ Image deleted successfully','Alert Dialog',function(){
									location.reload();
								});
							}else{
								jAlert('No image found','Alert Dialog',function(){
									location.reload();
								});
							}
						}			
					});
				}
		 	});
		}
		function fill_seller_status(){
			var sta = jQuery('#abpd_absupplier_id option:selected').attr('absl_status');
			var seller_type = jQuery('#abpd_absupplier_id option:selected').attr('absl_seller_type');
			jQuery('#abpd_seller_status').val(sta);
			if(seller_type=='local_store_seller'){
				jQuery('.local_store_row').removeClass('hidden');
				jQuery('.show_local_store_avail').removeClass('hidden');
				local_store_options = "<option value='N' class='hidden'>No</option>";
				local_store_options += "<option value='Y' selected=''>Yes</option>";
				local_store_options += "<option value='both' class='hidden'>Both</option>";
				jQuery('.abpd_local_store_avail').html(local_store_options);
			}else if(seller_type=='both'){
				jQuery('.local_store_row').removeClass('hidden');
				local_store_options = "<option value='N'>No</option>";
				local_store_options += "<option value='Y' selected=''>Yes</option>";
				local_store_options += "<option value='both'>Both</option>";
				jQuery('.abpd_local_store_avail').html(local_store_options);
				//jQuery('.show_local_store_avail').addClass('hidden');
			}else{
				local_store_options = "<option value='N' selected=''>No</option>";
				local_store_options += "<option value='Y' class='hidden'>Yes</option>";
				local_store_options += "<option value='both' class='hidden'>Both</option>";
				jQuery('.abpd_local_store_avail').html(local_store_options);
				jQuery('.abpd_local_store_dist').val('0.00');
				jQuery('.local_store_row').addClass('hidden');
			}
		}
		function getsubcat(state_id,selected = ''){
			
			if(state_id>0){
				jQuery('#ab_subcat_id').html('');
				var list = '<option value="">- - - Select Sub Category - - -</option>';
				jQuery.ajax({
					url:'get_subcat.php',
					type:'POST',	
					data:{state_id:state_id,iden:'dist'},
					dataType:'json',
					success:function(data){
						if(data!=null && data!=''){
							for(i=0;i<data.length;i++){
								var selectedtxt = (selected == data[i].ab_cat_id) ? 'selected="selected"' : '';
								list = list + '<option value="'+data[i].ab_cat_id+'" '+selectedtxt+'>'+data[i].ab_subcat_name+'</option>';
							}	
							jQuery('#ab_subcat_id').html(list);
						}
					}
				});
				calculate_fulfilment_charge();
			}else{
				jQuery('#ab_subcat_id').html('<option value="">- - - Select Sub Category - - -</option>');	
			}
		}
		function getsubsubcat(state_id,selected = ''){
			if(state_id>0){
				jQuery('#ab_childcat_id').html('');
				var list = '<option value="">- - - Select Child Sub Category - - -</option>';
				jQuery.ajax({
					url:'get_childcat.php',
					type:'POST',	
					data:{state_id:state_id,iden:'dist'},
					dataType:'json',
					success:function(data){
						if(data!=null && data!=''){
							for(i=0;i<data.length;i++){
								var selectedtxt = (selected == data[i].ab_cat_id) ? 'selected="selected"' : '';
								list = list + '<option value="'+data[i].ab_cat_id+'" '+selectedtxt+'>'+data[i].ab_subcat_name+'</option>';
							}
							jQuery('#ab_childcat_id').html(list);
						}
					}
				});
				calculate_fulfilment_charge();
			}else{
				jQuery('#ab_childcat_id').html('<option value="">- - - Select Child Sub Category - - -</option>');
			}
		}
		function calculate_fulfilment_charge(){
			var cat_id = jQuery("#abpd_cat_id").val();
			var sub_cat_id = jQuery("#ab_subcat_id").val();
			if(jQuery("#abpd_agribegri_fulfilment").is(':checked')){
				jQuery(".single_variation_group_wrap").each(function(){
					var variation = jQuery(this); 
					var shipping_commission='';
					var shipping_commission1='';
					var shipping_through = variation.find('.abpdv_shipping_through').val();
					var weight = Math.round(variation.find('.abpd_weight_packing').val());
					var hidden_shipped_by = variation.find('.hidden_shipped_by').val();
					var hidden_fulfilment_amount = variation.find('.hidden_fulfilment_amount').val();
					console.log("fulfilment_charge "+ hidden_fulfilment_amount);
					if(shipping_through!='Shipping Through Transport'){
						if(cat_id=='24' || cat_id=='39' || cat_id=='47'  || cat_id=='17' || (cat_id=='19' && sub_cat_id!='185')){
							jQuery("#abpd_agribegri_fulfilment").prop('checked', true);
							jQuery(".td_fulfilment_amount").show();
							var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
					    	jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
							    if(hidden_shipped_by == shippedBy){
							    	shipping_commission = commission_per;
							    }
							});
							var minus_shipping = shipping_commission - 4;
							var shipped_by_commissionJSON1 = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
					    	jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
							    if(commission_per == minus_shipping){
							    	shipping_commission1 = shippedBy;
							    }
							});
							if(hidden_fulfilment_amount<=0){
								//variation.find('.shipped_by').val(shipping_commission1);
							}
							if(weight > 0 && weight <= 1000){
								variation.find('.abpdv_fulfilment_amount').val(22);
							}else if(weight > 1001 && weight <= 2000){
								variation.find('.abpdv_fulfilment_amount').val(39);
							}else if(weight > 2001 && weight <= 4000){
								variation.find('.abpdv_fulfilment_amount').val(67);
							}else if(weight > 4001 && weight <= 7000){
								variation.find('.abpdv_fulfilment_amount').val(149);
							}else if(weight > 7001 && weight <= 8000){
								variation.find('.abpdv_fulfilment_amount').val(173);
							}else if(weight > 8001 && weight <= 9000){
								variation.find('.abpdv_fulfilment_amount').val(197);
							}else if(weight > 9001 && weight <= 10000){
								variation.find('.abpdv_fulfilment_amount').val(221);
							}else if(weight > 10001 && weight <= 14000){
								variation.find('.abpdv_fulfilment_amount').val(245);
							}else if(weight > 14000){
								var new_weight = Math.round(weight - 14000);
								var get_kg = Math.ceil(new_weight/1000);
								var per_kg_charge = (get_kg*20);
								fulfilment_amount = 245+per_kg_charge;
								variation.find('.abpdv_fulfilment_amount').val(fulfilment_amount);
							}
							else{
								variation.find('.abpdv_fulfilment_amount').val(0);
							}
						}else if((cat_id==18 && sub_cat_id==127) || cat_id==33 && sub_cat_id==145){
							jQuery("#abpd_agribegri_fulfilment").prop('checked', true);
							jQuery(".td_fulfilment_amount").show();
							var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
					    	jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
							    if(hidden_shipped_by == shippedBy){
							    	shipping_commission = commission_per;
							    }
							});
							var minus_shipping = shipping_commission - 4;
							var shipped_by_commissionJSON1 = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
					    	jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
							    if(commission_per == minus_shipping){
							    	shipping_commission1 = shippedBy;
							    }
							});
							if(hidden_fulfilment_amount<=0){
								variation.find('.shipped_by').val(shipping_commission1);
							}
							if(weight > 0 && weight <= 5000){
								variation.find('.abpdv_fulfilment_amount').val(20);
							}else if(weight > 5001 && weight <= 10000){
								variation.find('.abpdv_fulfilment_amount').val(40);
							}else if(weight > 10000){
								variation.find('.abpdv_fulfilment_amount').val(60);
							}
						}else{
							//jQuery("#abpd_agribegri_fulfilment").prop('checked', false);
							variation.find('.abpdv_fulfilment_amount').val(0);
							//variation.find('.td_fulfilment_amount').hide();
						}
					}else{
						//var cnt_fulfilment_amnt = variation.find('.abpdv_fulfilment_amount').length;
							//console.log(cnt_fulfilment_amnt);
						//jQuery("#abpd_agribegri_fulfilment").prop('checked', false);
						variation.find('.abpdv_fulfilment_amount').val(0);
						//variation.find('.td_fulfilment_amount').hide();
					}
					calculate_sale_price(variation);
				});
			}
		}
		function update_variation_code(){
			var prod_code = jQuery('#abpd_code').val();
			jQuery(".single_variation_group_wrap").each(function(){
				var variation = jQuery(this); 
				var unit = variation.find('.abpd_unit').val();
				var unit_qty = variation.find('.abpd_unit_quantity').val();
				var count = variation.find('.var_count').val();
				var isGenerated = variation.find('.abpdv_code').val();
				// console.log(isGenerated);
				if(isGenerated==""){
					jQuery.ajax({
						url:'add_prod_variation.php',
						data:{prod_code:prod_code,unit:unit,unit_qty:unit_qty,count:count,type:'generate_variation_code'},
						type:'POST',
						success:function(response){
							variation.find('.abpdv_code').val(response);
						}
					});
				}
			});
		}
		function reset_add_remove_btn(){
			console.log("reset_add_remove_btn");
			var add_btn = '<button type="button" class="btn btn-primary btn-large add_variation">Add Variation</button>';
			var variation_len = jQuery(".single_variation_group_wrap").length;
			/*** add add button in last action child ***/
			variation_count = 1;
			jQuery(".single_variation_group_wrap").each(function(){
				var head_note = '';
				if(jQuery(this).find('.abpd_unit_quantity').val()!=''){
					head_note += ' - '+jQuery(this).find('.abpd_unit_quantity').val();
				}
				if(jQuery(this).find('.abpd_unit').val()!=''){
					head_note += ' '+jQuery(this).find('.abpd_unit').val();
				}
				if(jQuery(this).find('.abpdv_notes').val()!=''){
					head_note += ' - '+jQuery(this).find('.abpdv_notes').val();
				}
				jQuery(this).find('thead h4').text('Variation '+variation_count+head_note);
				jQuery(this).find('.var_count').val(variation_count);
				jQuery(this).find('.is_default_variation').attr('name', 'is_default_variation_'+variation_count);
				jQuery(this).find('.abpdv_delhivery').attr('name', 'delhivery_'+variation_count);
				/*** add variation button last variation ****/
				if(variation_count == variation_len){
					jQuery(".single_variation_group_wrap:last-child").find('tr').last().find('.add_remove_variaiton_wrap').html(add_btn);	
				}
				/*** remove add variation button from all variation esc. last child ***/
				if(variation_count < variation_len){
					jQuery(this).find('.add_variation').remove();				
				}
				variation_count++;
			});
			// update variation code show
			update_variation_code();
		}
		function open_variation_group_for_validation(variation){
			if(variation.hasClass('closed')){
				variation.addClass('opened');
				variation.find('tbody').show('slow');
				variation.removeClass('closed');
				variation.find('.collaspe').text('-');
			}
		}
		function calculate_sale_price(variationdiv){
			var seller_id_arr = <?php echo json_encode($seller_skip_Arr); ?>;
			var seller_id = jQuery("#abpd_absupplier_id").val();
			var price = variationdiv.find('.abpd_price').val();
			price = (price == '') ? 0 : price;
			var fulfilment_charge = variationdiv.find('.abpdv_fulfilment_amount').val();
			fulfilment_charge = (fulfilment_charge == '') ? 0 : fulfilment_charge;
			var discount = variationdiv.find('.abpd_discount').val();
			var today_offer_avail = variationdiv.find('.abpd_today_offer').val();
			if(today_offer_avail == 'Y'){
				discount = variationdiv.find('.abpd_today_discount').val();
			}
			discount = (discount == '') ? 0 : discount;
			var saleprice = (parseFloat(price)  - parseFloat(discount)).toFixed(2);
			//if(!seller_id_arr.includes(seller_id)){
				variationdiv.find('.abpd_sale_price').val(saleprice);
			//}
			/*** SELLER WILL GET AMOUNT CALCULATION ***/
			var shipped_by = variationdiv.find(".shipped_by").val();
			var postal_charge = variationdiv.find(".abpd_post_charge").val();
			var shipping_commission = '0';
			var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
			var sellerwillget = 0;
			var gst_product = 0;
			var total_commission = 0;
			jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
			    if(shipped_by == shippedBy){
			    	shipping_commission = commission_per;
			    	return false;
			    }
			});
			/*if(eval(price) < eval(postal_charge)){
				jAlert('Please enter courier fee less then MRP of product!','Alert Dialog', function(){
					variationdiv.find(".abpd_post_charge").val('').focus();
				});			
			}else{*/
				if(shipped_by != ''){
					//console.log(saleprice + ", " + gst_product + ", " + total_commission + ", " +shipping_commission + ", " + postal_charge);
					var sellerwillget = ((saleprice*100)/(100+parseFloat(gst_product))) - (saleprice*total_commission/100) - ((saleprice*shipping_commission)/100) - postal_charge - parseFloat(fulfilment_charge);
					console.log("S : " + saleprice + "== GST :" + gst_product + "==> Comm : " + total_commission + "== SHIPPING COmm. : " + shipping_commission+ "==> PC : " + postal_charge);
				}
					//if(!seller_id_arr.includes(seller_id)){
					if(shipped_by.indexOf('Self') != -1){
					    sellerwillget = saleprice - (saleprice*(shipping_commission/100));
					    variationdiv.find(".abpd_seller_amount").val(parseFloat(sellerwillget).toFixed(2));
					}else{
						variationdiv.find(".abpd_seller_amount").val(parseFloat(sellerwillget).toFixed(2));
					}
				//}					
			/*}*/
		}
		function calculate_courier_charge(variationdiv){
			var seller_id_arr = <?php echo json_encode($seller_skip_Arr); ?>;
			var seller_id = jQuery("#abpd_absupplier_id").val(); <?php //echo $_GET['seller_id']; ?>
			var abpd_weight = Math.round(variationdiv.find('.abpd_weight_packing').val());
			/*var abpd_volumetric_weight = Math.round(variationdiv.find('.abpd_volumetric_weight').val());
			abpd_volumetric_weight = (abpd_volumetric_weight*1000);
			var abpd_weight_packing = Math.max(abpd_weight, abpd_volumetric_weight);*/
			var abpd_weight_packing = abpd_weight;
			var abpd_sale_price = Math.round(variationdiv.find('.abpd_sale_price').val());
			var courier_charge =0;
			var cod_charge_per = <?php echo COD_CHARGE_PER; ?>;
			var cod_charge_above_twenty = <?php echo COD_CHARGE_ABOVE_TWENTY; ?>;
			var cod_fixed_charge = <?php echo COD_FIXED_CHARGE; ?>;
			var abpdv_post_charge_without_extra =0;
			var abpdv_post_charge_extra =0;
			var payment_method = variationdiv.find('.abpdv_payment_method').val();
			var shipped_by = variationdiv.find(".shipped_by").val();
			var delhivery_check = variationdiv.find('.abpdv_delhivery:checked').val();
			if(delhivery_check=='Y'){
				if(abpd_weight_packing <=500){
					courier_charge = abpdv_post_charge_without_extra = 50.00;
				}else if(abpd_weight_packing >500 && abpd_weight_packing <=1000){
					courier_charge = abpdv_post_charge_without_extra = 70.00;
				}else if(abpd_weight_packing >1000 && abpd_weight_packing <=2000){
					courier_charge = abpdv_post_charge_without_extra = 100.00;
				}else if(abpd_weight_packing >2000 && abpd_weight_packing <=3000){
					courier_charge = abpdv_post_charge_without_extra = 140.00;
				}else if(abpd_weight_packing >3000 && abpd_weight_packing <=5000){
					courier_charge = abpdv_post_charge_without_extra = 200.00;
				}else if(abpd_weight_packing >5000 && abpd_weight_packing <=6000){
					courier_charge = abpdv_post_charge_without_extra = 265.00;
				}else if(abpd_weight_packing >6000 && abpd_weight_packing <=7000){
					courier_charge = abpdv_post_charge_without_extra = 310.00;
				}else if(abpd_weight_packing >7000 && abpd_weight_packing <=8000){
					courier_charge = abpdv_post_charge_without_extra = 355.00;
				}else if(abpd_weight_packing >8000 && abpd_weight_packing <=10000){
					courier_charge = abpdv_post_charge_without_extra = 400.00;
				}/*else if(abpd_weight_packing >10000 && abpd_weight_packing <=12000){
					courier_charge = abpdv_post_charge_without_extra = 420.00;
				}else if(abpd_weight_packing >12000 && abpd_weight_packing <=20000){
					courier_charge = abpdv_post_charge_without_extra = 470.00;
				}*/else if(abpd_weight_packing >10000){
					var get_kg = Math.ceil(abpd_weight_packing/1000);
					//console.log(get_kg);
					//var above_twenty = abpd_weight_packing-10000;
					var per_kg = get_kg-10;//above_twenty/1000;
					var per_kg_charge = (per_kg*cod_charge_above_twenty);
					courier_charge = abpdv_post_charge_without_extra = 400.00+per_kg_charge;
				}
			}else{
				if(abpd_weight_packing <=500){
					courier_charge = abpdv_post_charge_without_extra = 80.00;
				}else if(abpd_weight_packing >500 && abpd_weight_packing <=1000){
					courier_charge = abpdv_post_charge_without_extra = 112.00;
				}else if(abpd_weight_packing >1000 && abpd_weight_packing <=2000){
					courier_charge = abpdv_post_charge_without_extra = 140.00;
				}else if(abpd_weight_packing >2000 && abpd_weight_packing <=5000){
					courier_charge = abpdv_post_charge_without_extra = 220.00;
				}else if(abpd_weight_packing >5000 && abpd_weight_packing <=6000){
					courier_charge = abpdv_post_charge_without_extra = 265.00;
				}else if(abpd_weight_packing >6000 && abpd_weight_packing <=7000){
					courier_charge = abpdv_post_charge_without_extra = 310.00;
				}else if(abpd_weight_packing >7000 && abpd_weight_packing <=8000){
					courier_charge = abpdv_post_charge_without_extra = 355.00;
				}else if(abpd_weight_packing >8000 && abpd_weight_packing <=10000){
					courier_charge = abpdv_post_charge_without_extra = 400.00;
				}/*else if(abpd_weight_packing >10000 && abpd_weight_packing <=12000){
					courier_charge = abpdv_post_charge_without_extra = 420.00;
				}else if(abpd_weight_packing >12000 && abpd_weight_packing <=20000){
					courier_charge = abpdv_post_charge_without_extra = 470.00;
				}*/else if(abpd_weight_packing >10000){
					var get_kg = Math.ceil(abpd_weight_packing/1000);
					//console.log(get_kg);
					//var above_twenty = abpd_weight_packing-10000;
					var per_kg = get_kg-10;//above_twenty/1000;
					var per_kg_charge = (per_kg*cod_charge_above_twenty);
					courier_charge = abpdv_post_charge_without_extra = 400.00+per_kg_charge;
				}
			}
			if(payment_method=='online')
			{
				var courier_per_charge = 	abpd_sale_price * (2 / 100);
				var courier_extra_charge = courier_per_charge;//Math.max(courier_per_charge, cod_fixed_charge);
				courier_charge = courier_charge+courier_extra_charge;
				courier_charge = parseFloat(courier_charge).toFixed(2);
				abpdv_post_charge_extra = parseFloat(courier_extra_charge).toFixed(2);
			}else{
				/*if(abpd_sale_price <=1500 && abpd_weight_packing <=20000){*/
				if(abpd_sale_price <=1500){
					courier_charge = courier_charge+cod_fixed_charge;
					courier_charge = parseFloat(courier_charge).toFixed(2);
					abpdv_post_charge_extra = parseFloat(cod_fixed_charge).toFixed(2);
				/*}else if(abpd_sale_price >1500 && abpd_weight_packing >20000){
					courier_charge = parseFloat(courier_charge).toFixed(2);
				}else if(abpd_sale_price >1500 && abpd_weight_packing <=20000){*/
				}else if(abpd_sale_price >1500){
					var courier_per_charge = abpd_sale_price*(cod_charge_per/100);
					var courier_extra_charge = Math.max(courier_per_charge, cod_fixed_charge);
					courier_charge = courier_charge+courier_extra_charge;
					courier_charge = parseFloat(courier_charge).toFixed(2);
					/*if(abpd_weight_packing >20000){
						abpdv_post_charge_extra = courier_extra_charge + per_kg_charge;
						abpdv_post_charge_extra = parseFloat(abpdv_post_charge_extra).toFixed(2);
					}else{*/
						abpdv_post_charge_extra = parseFloat(courier_extra_charge).toFixed(2);
					/*}*/
					//console.log(abpd_sale_price*(cod_charge_per/100));
				}
			}
			abpdv_post_charge_without_extra = parseFloat(abpdv_post_charge_without_extra).toFixed(2);
			var abpdv_shipping_through = variationdiv.find('.abpdv_shipping_through').val();
			console.log(courier_charge);
			if(abpdv_shipping_through!='Shipping Through Transport'){
				//if(!seller_id_arr.includes(seller_id)){
				if(shipped_by.indexOf('Self') != -1){
					variationdiv.find('.abpd_post_charge').val('0.00');
					variationdiv.find('.abpdv_post_charge_without_extra').val('0.00');
					variationdiv.find('.abpdv_post_charge_extra').val('0.00');
				}else{
					variationdiv.find('.abpd_post_charge').val(courier_charge);
					variationdiv.find('.abpdv_post_charge_without_extra').val(abpdv_post_charge_without_extra);
					variationdiv.find('.abpdv_post_charge_extra').val(abpdv_post_charge_extra);
				}
				//}
			}else{
				variationdiv.find('.abpd_post_charge').val('0.00');
				variationdiv.find('.abpdv_post_charge_without_extra').val('0.00');
				variationdiv.find('.abpdv_post_charge_extra').val('0.00');
			}
			calculate_sale_price(variationdiv);
		}
		function calculate_mrp_discount(variationdiv){
			var abpd_price = variationdiv.find('.abpd_price').val();
			var abpd_today_discount = variationdiv.find('.abpd_today_discount').val();
			var abpd_discount = variationdiv.find('.abpd_discount').val();
			var abpd_today_offer = variationdiv.find('.abpd_today_offer').val();
			var total_discount_percent = 0;
			if(abpd_today_offer == 'Y' && abpd_today_discount != '' && abpd_today_discount != '0.00'){
				total_discount_percent = (abpd_today_discount * 100) / abpd_price; 
			}else if(abpd_discount != "" && abpd_discount > 0){
				total_discount_percent = (abpd_discount * 100) / abpd_price;
			}
			total_discount_percent=Math.round(total_discount_percent);
			variationdiv.find('.abpdv_mrp_discount').val(total_discount_percent);
		}
		function isNumber(evt) {
			evt = (evt) ? evt : window.event;
			var charCode = (evt.which) ? evt.which : evt.keyCode;
			if (charCode > 31 && (charCode < 48 || charCode > 57)) {
				return false;
			}
				return true;
		}
		function get_seller_price(seller_id,variation_id,cnt){
			var product_id = '<?php echo $abpd_id; ?>';
			jQuery.ajax({
				url:'add_prod_variation.php',
				data:{seller_id:seller_id,variation_id:variation_id,product_id:product_id,type:'get_seller_price'},
				dataType:'JSON',
				type:'POST',
				success:function(response){
					jQuery("#abpd_price_"+cnt).val(response.price);
					jQuery("#abpd_discount_"+cnt).val(response.discount_rs);
					jQuery("#abpdv_sale_price_"+cnt).val(response.sale_price);
					jQuery("#abpd_qty_"+cnt).val(response.qty);
					var variationdiv =(jQuery("#abpd_price_"+cnt).parents('.single_variation_group_wrap'));
					calculate_courier_charge(variationdiv);
					calculate_sale_price(variationdiv);
				}
			});
		}
		jQuery(document).ready(function($){
			jQuery('.abpdv_exp_date').datepicker({
				changeMonth:true,
				changeYear:true,
				dateFormat:'yy-mm-dd',
				showButtonPanel: true,
            closeText: 'Clear', // Text to show for "close" button
            onClose: function () {
               var event = arguments.callee.caller.caller.arguments[0];
                // If "Clear" gets clicked, then really clear it
               if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
                  $(this).val('');
               }
            }
			}).focus(function () {
            $(".ui-datepicker-current").hide();
         });
			$('#ab_cropcat_id').multiselect({ 
				includeSelectAllOption: true,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				maxHeight: 170 ,
				nonSelectedText:'Select Crop Category',
				numberDisplayed: 0,       
			});
			$('#abpd_season_month').multiselect({ 
				includeSelectAllOption: true,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				maxHeight: 170 ,
				nonSelectedText:'Select Month(s)',
				numberDisplayed: 0,       
			});
			$('#abpd_state_compaign_month').multiselect({ 
				includeSelectAllOption: true,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				maxHeight: 170 ,
				nonSelectedText:'Select Month',
				numberDisplayed: 0,       
			});
			$('#abpd_state_id').multiselect({ 
				includeSelectAllOption: true,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				maxHeight: 170 ,
				nonSelectedText:'Select State',
				numberDisplayed: 0,       
			});
			$('#absd_state_id').multiselect({ 
				includeSelectAllOption: true,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				maxHeight: 170 ,
				nonSelectedText:'Select State',
				numberDisplayed: 0,       
			});
			$('.abpdv_variation_color').multiselect({ 
				includeSelectAllOption: true,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				maxHeight: 170 ,
				nonSelectedText:'Select color',
				numberDisplayed: 0,       
			});
			$('#abrs_state_id').multiselect({ 
				includeSelectAllOption: true,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				maxHeight: 170 ,
				nonSelectedText:'Select State',
				numberDisplayed: 0,       
			});
			CKEDITOR.replace( 'abpd_descri', {
			  filebrowserBrowseUrl :'ckeditors/filemanager/browser/default/browser.html?Connector=<?php echo $SITE_TITLE_ADMIN_URL; ?>ckeditors/connectors/php/connector.php',
			  filebrowserImageBrowseUrl : 'ckeditors/filemanager/browser/default/browser.html?Type=Image&Connector=<?php echo $SITE_TITLE_ADMIN_URL; ?>ckeditors/filemanager/connectors/php/connector.php',
			  filebrowserFlashBrowseUrl :'ckeditors/filemanager/browser/default/browser.html?Type=Flash&Connector=<?php echo $SITE_TITLE_ADMIN_URL; ?>ckeditors/filemanager/connectors/php/connector.php',
				filebrowserUploadUrl  :'<?php echo $SITE_TITLE_ADMIN_URL; ?>ckeditors/filemanager/connectors/php/upload.php?Type=File',
				filebrowserImageUploadUrl : '<?php echo $SITE_TITLE_ADMIN_URL; ?>ckeditors/filemanager/connectors/php/upload.php?Type=Image',
				filebrowserFlashUploadUrl : '<?php echo $SITE_TITLE_ADMIN_URL; ?>ckeditors/filemanager/connectors/php/upload.php?Type=Flash'
			});
			// Recommanded Product listing start
			var count_prod = 1;
			$('#recommanded_prod td input[type=checkbox]:checked').each(function(){
				var tdValue = $(this).val();
				var parent = $(this).closest('tr').find('td').text();
				$("#recommandedProd").append('<br>'+count_prod+' - '+parent);
				count_prod++;
			});
			jQuery('body').on('change', ".abpd_recomanded_prod", function(){
				$("#recommandedProd").html('');
				var count_prod = 1;
				$('#recommanded_prod td input[type=checkbox]:checked').each(function(){
					var tdValue = $(this).val();
					var parent = $(this).closest('tr').find('td').text();
					$("#recommandedProd").append('<br>'+count_prod+' - '+parent);
					count_prod++;
				});
			});
			jQuery('body').on('keyup change focusout', ".dataTables_filter input[type=text]", function(){
				$("#recommandedProd").html('');
				var count_prod = 1;
				$('#recommanded_prod td input[type=checkbox]:checked').each(function(){
					var tdValue = $(this).val();
					var parent = $(this).closest('tr').find('td').text();
					$("#recommandedProd").append('<br>'+count_prod+' - '+parent);
					count_prod++;
				});
			});
			// Recommanded Product listing end
			// Similar Product listing start
			var count_prod_similar = 1;
			$('#similar_prod td input[type=checkbox]:checked').each(function(){
				var tdValueSimilar = $(this).val();
				var parentSimilar = $(this).closest('tr').find('td').text();
				var code = $(this).closest('tr').find('.code').val();
				var mainProd = $("#main_product").val();
				var addcls = '';
				if(mainProd == tdValueSimilar){
					var addcls = 'class="mainProd"';
				}
				$("#similarProd").append('<label '+addcls+'>'+count_prod_similar+' - '+parentSimilar+' ( '+code+' )</label>');
				count_prod_similar++;
			});
			var mainProd = $("#main_product").val();
			if(mainProd != ''){
				$("#similar_prod_"+mainProd+" td:nth-child(2)").addClass("mainProd");
			}
			// show checkbox check uncheck listing
			jQuery('body').on('change', ".abpd_similar_prod", function(){
				$("#similarProd").html('');
				var count_prod_similar = 1;
				var mainProd = $("#main_product").val();
				if(mainProd != ''){
					$("#similar_prod_"+mainProd+" td:nth-child(2)").addClass("mainProd");
				}
				$('#similar_prod td input[type=checkbox]:checked').each(function(){
					var tdValueSimilar = $(this).val();
					var parentSimilar = $(this).closest('tr').find('td').text();
					var code = $(this).closest('tr').find('.code').val();
					var mainProd = $("#main_product").val();
					var addcls = '';
					if(mainProd == tdValueSimilar){
						var addcls = 'class="mainProd"';
					}
					$("#similarProd").append('<label '+addcls+'>'+count_prod_similar+' - '+parentSimilar+' ( '+code+' )</label>');
					count_prod_similar++;
				});
			});
			// search mate nu listing che
			jQuery('body').on('keyup change focusout', "#similar_prod .dataTables_filter input[type=text]", function(){
				$("#similarProd").html('');
				var count_prod_similar = 1;
				var mainProd = $("#main_product").val();
				if(mainProd != ''){
					$("#similar_prod_"+mainProd+" td:nth-child(2)").addClass("mainProd");
				}
				$('#similar_prod td input[type=checkbox]:checked').each(function(){
					var tdValueSimilar = $(this).val();
					var parentSimilar = $(this).closest('tr').find('td').text();
					var code = $(this).closest('tr').find('.code').val();
					var mainProd = $("#main_product").val();
					var addcls = '';
					if(mainProd == tdValueSimilar){
						var addcls = 'class="mainProd"';
					}
					$("#similarProd").append('<label '+addcls+'>'+count_prod_similar+' - '+parentSimilar+' ( '+code+' )</label>');
					count_prod_similar++;
				});
			});
			// Similar Product listing end
        	// Same Product listing start
			var count_prod_same = 1;
			$('#same_prod td input[type=checkbox]:checked').each(function(){
				var parentSame = $(this).closest('tr').find('td').text();
				var code = $(this).closest('tr').find('.code').val();
				$("#sameProd").append('<label>'+count_prod_same+' - '+parentSame+' ( '+code+' )</label>');
				count_prod_same++;
			});
			
			// show checkbox check uncheck listing
			jQuery('body').on('change', ".abpd_same_prod", function(){
				$("#sameProd").html('');
				var count_prod_same = 1;
				$('#same_prod td input[type=checkbox]:checked').each(function(){
					var parentSame = $(this).closest('tr').find('td').text();
					var code = $(this).closest('tr').find('.code').val();
					$("#sameProd").append('<label>'+count_prod_same+' - '+parentSame+' ( '+code+' )</label>');
					count_prod_same++;
				});
			});
			// search mate nu listing che
			jQuery('body').on('keyup change focusout', "#same_prod .dataTables_filter input[type=text]", function(){
				$("#sameProd").html('');
				var count_prod_same = 1;
				$('#same_prod td input[type=checkbox]:checked').each(function(){
					var parentSame = $(this).closest('tr').find('td').text();
					var code = $(this).closest('tr').find('.code').val();
					$("#sameProd").append('<label>'+count_prod_same+' - '+parentSame+' ( '+code+' )</label>');
					count_prod_same++;
				});
			});
			// Same Product listing end
			jQuery('[data-toggle="tooltip"]').tooltip();
			jQuery('#recomandedn_table').dataTable({
				"bPaginate": false,
				"bFilter": true,
				"bordering": false,
				"bSort" : false,
				"bInfo": false
			});
			jQuery('#similar_prod_table').dataTable({
				"bPaginate": false,
				"bFilter": true,
				"bordering": false,
				"bSort" : false,
				"bInfo": false
			});
        	jQuery('#same_prod_table').dataTable({
				"bPaginate": false,
				"bFilter": true,
				"bordering": false,
				"bSort" : false,
				"bInfo": false
			});
			jQuery('.abpd_till_date').datepicker({
				changeMonth:true,
				changeYear:true,
				dateFormat:'yy-mm-dd',			
			});
			jQuery("#abpd_product_tags").tagsInput({'height':'100px','width':'60%'});
			//jQuery('input[name="abpdv_allowed_postcode[]"]').tagsInput({'height':'72px','width':'215px'});
			jQuery('.numer_only').on('keydown',function(e){
				var val = jQuery(this).val();
				if (e.keyCode == 110 && val.indexOf('.') !== -1){
					e.preventDefault();
					return false;
				}
				if (jQuery.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 || (e.keyCode == 65 && e.ctrlKey === true) || (e.keyCode >= 35 && e.keyCode <= 39)) {
		             return;
					}
		    	if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
		        	e.preventDefault();
		    	}
			});
			jQuery('body').on('input','.number_without_decimal',function(e){
		        this.value = this.value
			      .replace(/[^\d]/g, '')             // numbers and decimals only
			      ;   
			});
			// only for product name validation
			jQuery('.min_max_char_validation').keyup(function(e) {
				var tval = jQuery(this).val(),
				tlength = tval.length,
				set = jQuery(this).attr('maxlength'),
				minset = jQuery(this).attr('minlength'),
				remain = parseInt(set - tlength);
				// if(minset < tlength && set >= tlength ){
				// 	// console.log("TL:" + tlength + ", S:"+ set + ", MS:" + minset + ", R:"+remain);	
				// }
				var key = e.keyCode;
				// console.log(key + "L:"+ tlength + "S:"+ set + "MS:" + minset + "R:"+remain);
				if(key != 8 && key != 46){
					if(jQuery(this).parent().find('.remains-character').length <=0 && remain > 0){
						jQuery('<span class="remains-character">'+(tlength )+' characters.</span>').insertAfter($(this).parent().find(".maximum-character"));
						jQuery(this).parent().find('span.error').text('').hide();
					}else if(remain > 0){
						jQuery(this).parent().find('.remains-character').text((tlength)+' characters.');
						jQuery(this).parent().find('span.error').text('').hide();
					}else{
						jQuery(this).parent().find('.remains-character').text((tlength)+' characters.');
						/*if(eval(jQuery(this).parent().find('span.error').length) <= 0){
							$(this).parent().append('<span class="error" > (Product name must be of minimum '+minset+' characters and maximum '+set+' characters.)</span>');
						}*/
						return false;
					}
				}else{
					if(jQuery(this).parent().find('.remains-character').length <=0 && remain > 0){
						jQuery('<span class="remains-character">'+(tlength)+' characters.</span>').insertAfter(jQuery(this).parent().find(".maximum-character"));
						jQuery(this).parent().find('span.error').text('').hide();
					}else if(remain > 0){
						jQuery(this).parent().find('.remains-character').text((tlength)+' characters.');
						jQuery(this).parent().find('span.error').text('').hide();
					}else{
						jQuery(this).parent().find('.remains-character').text((tlength)+' characters.');
						/*if(eval($(this).parent().find('span.error').length) <= 0){
							jQuery(this).parent().append('<span class="error" > (Product name must be of minimum '+minset+' characters and maximum '+set+' characters.)</span>');
						}*/
					}
				}
				// console.log("TEST : " + remain);
			});
			jQuery('.max_char_validation').keyup(function(e) {
				var tval = jQuery(this).val(),
				tlength = tval.length,
				set = jQuery(this).attr('maxlength'),
				remain = parseInt(set - tlength);
				var key = e.keyCode;
				// console.log(key + "L:"+ tlength + "S:"+ set + "R:"+remain);
				if(key != 8 && key != 46){
					if(jQuery(this).parent().find('.remains-character').length <=0 && remain > 0){
						jQuery('<span class="remains-character">'+(remain )+' characters remains.</span>').insertAfter($(this).parent().find(".maximum-character"));
						jQuery(this).parent().find('span.error').text('').hide();
					}else if(remain > 0){
						jQuery(this).parent().find('.remains-character').text((remain)+' characters remains.');
						jQuery(this).parent().find('span.error').text('').hide();
					}else{
						jQuery(this).parent().find('.remains-character').text('0 characters remains.');
						if(eval(jQuery(this).parent().find('span.error').length) <= 0){
							$(this).parent().append('<span class="error" >Please enter no more than '+set+' characters.</span>');
						}
						return false;
					}
				}else{
					if(jQuery(this).parent().find('.remains-character').length <=0 && remain > 0){
						jQuery('<span class="remains-character">'+(remain)+' characters remains.</span>').insertAfter(jQuery(this).parent().find(".maximum-character"));
						jQuery(this).parent().find('span.error').text('').hide();
					}else if(remain > 0){
						jQuery(this).parent().find('.remains-character').text((remain)+' characters remains.');
						jQuery(this).parent().find('span.error').text('').hide();
					}else{
						jQuery(this).parent().find('.remains-character').text('0 characters remains.');
						if(eval($(this).parent().find('span.error').length) <= 0){
							jQuery(this).parent().append('<span class="error" >Please enter no more than '+set+' characters.</span>');
						}
					}
				}
				// console.log("TEST : " + remain);
			});
			jQuery('.numer_only').on('blur',function(){
				var val = jQuery(this).val();
				if(val!='' && val != null){
					val = parseFloat(val).toFixed(2);
				}else{
					val = '';
				}
				jQuery(this).val(val);
			});
			jQuery('.uplaod_image').live('click',function(){
				var url = jQuery(this).attr('rel');
				jQuery.ajax({
					url:url,
					type:'POST',
					success:function(data){
						jQuery('#mymodalhtml').html(data);
						jQuery('#imageModal').modal('show');
					}
				});
			});
			jQuery("#prdct_frm input,#prdct_frm .cke").on('keypress',function(){
				if(jQuery(this).val()!=''){
					jQuery(this).css('border','1px solid #bbb');
				}
			});
			jQuery("#prdct_frm select").on('change',function(){
				if(jQuery(this).val()!=''){
					jQuery(this).css('border','1px solid #bbb');
				}
			});
			$("body").on("click", ".add_variation", function(e){
				console.log('add_variation');
				/*** get html from first variation ***/
				var tablehtmlobj = $('<tr><td><table class="single_variation_group_wrap opened add_variable">'+$(this).parents(".single_variation_group_wrap").html()+"</table></td></tr>");
				var variation_table = $("#variationTable");
				/**** reset value of inputs ***/
				tablehtmlobj.find('input, select, span').each(function(index, elem){
					// console.log($(this).prop('className'));
					// console.log(elem);
					var inputType = $(this).attr('type');
					var className = $(this).attr('class');
					var currentIndex = $(this).data('rowindex');
					var nextIndex = parseInt(currentIndex) + parseInt(1);
					console.log('className: '+className);
					if($(this).prop('tagName') == 'SELECT' || $(this).prop('tagName') == 'select'){
						$(elem).find('option:selected').removeAttr('selected');
						if( className == 'select-large abpd_in_stock required' ) {
							$(this).removeAttr('id')
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "abpd_in_stock_"+nextIndex);
						}
						if(className == 'select-large shipped_by required') {
							$(elem).find('option:disabled').removeAttr('disabled');
						}
					}else{
						if( className == 'input-large numer_only abpd_qty required' ) {
							$(this).removeAttr('id')
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "abpd_qty_"+nextIndex);
						}
						if( className == 'input-large numer_only abpd_price required' ) {
							$(this).removeAttr('id')
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "abpd_price_"+nextIndex);
						}
						if( className == 'input-large numer_only abpd_discount' ) {
							$(this).removeAttr('id');
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "abpd_discount_"+nextIndex);
						}
						if( className == 'vairant_shipping_per' ) {
							$(this).removeAttr('id');
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "vairant_shipping_per_"+nextIndex);
						}
						if( className == 'input-large numer_only abpd_sale_price' ) {
							$(this).removeAttr('id');
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "abpdv_sale_price_"+nextIndex);
						}
						if( className == 'input-file-cts abpdv_post_charge_without_extra numer_only required' ) {
							$(this).removeAttr('id');
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "abpdv_post_charge_without_extra_"+nextIndex);
						}
						if( className == 'input-file-cts abpdv_post_charge_extra numer_only required' ) {
							$(this).removeAttr('id');
							$(this).removeAttr('data-rowindex');
							$(this).attr('data-rowindex', nextIndex);
							$(this).attr('id', "abpdv_post_charge_extra_"+nextIndex);
						}
						/*if(className == 'abpdv_delhivery'){
							console.log(nextIndex);
							$(this).removeAttr('name');
							$(this).attr('name', "delhivery_"+nextIndex);
						}*/
						if(inputType == 'checkbox' || inputType == 'radio'){
							$(this).attr('checked', false);
						}else{
							$(this).removeAttr('value');
						}
					}
					if(className == 'select-large abpdv_variation_color'){
						$(this).removeAttr('id');
						$(this).removeAttr('data-rowindex');
						$(this).attr('data-rowindex', nextIndex);
						$(this).attr('id', "abpdv_variation_color_"+nextIndex);
						$(this).attr('name', "abpdv_variation_color_"+nextIndex+"[]");
					}
					if($(this).hasClass('required')){
						$(this).css('border', '1px solid #ff0000');
					}
				});
				//tablehtmlobj.find('.abpd_till_date').replaceWith('<input type="text" name="abpd_till_date[]" class="input-large abpd_till_date" value="" />');
				tablehtmlobj.find(".abpd_min_order_qty").attr('value','1');
				tablehtmlobj.find(".show_today_offer_active").addClass('hidden');
				tablehtmlobj.find(".show_local_store_avail").addClass('hidden');
				$("#variationTable > tbody").append('<tr>'+tablehtmlobj.html()+"</tr>");
				reset_add_remove_btn();
				//console.log($("#variationTable .single_variation_group_wrap:last").find('.abpd_till_date'));
				$("#variationTable .single_variation_group_wrap:last").find('.abpd_till_date').removeAttr('id').removeClass('hasDatepicker').datepicker({changeMonth:true,
				changeYear:true,
				dateFormat:'yy-mm-dd',});
				$("#variationTable .single_variation_group_wrap:last").find('.abpdv_exp_date').removeAttr('id').removeClass('hasDatepicker').datepicker({changeMonth:true,
				changeYear:true,
				dateFormat:'yy-mm-dd',
				showButtonPanel: true,
            closeText: 'Clear', // Text to show for "close" button
            onClose: function () {
               var event = arguments.callee.caller.caller.arguments[0];
                // If "Clear" gets clicked, then really clear it
               if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
                  $(this).val('');
               }
            }}).focus(function () {
	            $(".ui-datepicker-current").hide();
	         });
				jQuery(".abpdv_variation_color").multiselect('rebuild');
				jQuery(jQuery(".abpdv_variation_color").closest('td').find('p').nextAll()).each(function() {
					jQuery(this).remove();
				});
				//AUTO DELHIVERY CHARGE CALCULATION
				var technical_shipped_by = jQuery("#abpd_technical_id").find(':selected').attr('data-technical_shipped_by');
				var technical_non_shipped_by = jQuery("#abpd_technical_id").find(':selected').attr('data-technical_non_shipped_by');
				var comp_id = jQuery("#abpd_comp_id").find(':selected').attr('data-is_branded');
				$("#variationTable .single_variation_group_wrap:last").find('.shipped_by option:not(:selected)').attr('disabled', false);
				if(jQuery.type(technical_shipped_by) === "undefined"){
		    		technical_shipped_by = '';
				}
				if(jQuery.type(technical_non_shipped_by) === "undefined"){
		    		technical_non_shipped_by = '';
				}
				if(jQuery.type(comp_id) === "undefined"){
		    		comp_id = '';
				}
				var allow_disabled = 0;
				jQuery(".shipped_by option").each(function(i){
					var shipped_by_val = this.value;//jQuery(this).val();
					if(comp_id=='Y'){
						//if(shipped_by_val == 'Agribegri'){
						if(shipped_by_val == technical_shipped_by){
							allow_disabled = 1;
						}
					}
					if(technical_shipped_by!='' || technical_non_shipped_by!=''){
                    if(shipped_by_val == technical_shipped_by || shipped_by_val == technical_non_shipped_by){
                        allow_disabled = 1;
                    }
                }
		    	});
		    	/*var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
		    	jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
				    if(technical_shipped_by == shippedBy){
				    	shipping_commission = commission_per;
				    }
				});*/
		    	if(allow_disabled==1){
					if(comp_id=='Y'){
						//$("#variationTable .single_variation_group_wrap:last").find(".shipped_by").val('Agribegri');
						$("#variationTable .single_variation_group_wrap:last").find(".shipped_by").val(technical_shipped_by);
						$("#variationTable .single_variation_group_wrap:last").find('.shipped_by option:not(:selected)').attr('disabled', true);
						$("#variationTable .single_variation_group_wrap:last").find(".abpdv_payment_method").val('online');
						$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="cod"]').hide();
						$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="both"]').hide();
						//$("#variationTable .single_variation_group_wrap:last").find('.abpdv_delhivery').val('Y');
						//calculate_courier_charge($("#variationTable .single_variation_group_wrap:last"));
					}else if(technical_non_shipped_by!='' && (comp_id=='N' || comp_id=='')){
						//$("#variationTable .single_variation_group_wrap:last").find(".shipped_by").val(technical_shipped_by);
						$("#variationTable .single_variation_group_wrap:last").find(".shipped_by").val(technical_non_shipped_by);
						$("#variationTable .single_variation_group_wrap:last").find('.shipped_by option:not(:selected)').attr('disabled', true);
						var shipped_by = $("#variationTable .single_variation_group_wrap:last").find(".shipped_by").val();
                    var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
                    jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
                        if(shipped_by == shippedBy){
                            shipping_commission = commission_per;
                        }
                    });
						if(shipping_commission<=15){
							$("#variationTable .single_variation_group_wrap:last").find(".abpdv_payment_method").val('online');
							$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="cod"]').hide();
							$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="both"]').hide();
							//$("#variationTable .single_variation_group_wrap:last").find('.abpdv_delhivery').val('Y');
							//calculate_courier_charge($("#variationTable .single_variation_group_wrap:last"));
						}else{
							$("#variationTable .single_variation_group_wrap:last").find(".abpdv_payment_method").val('');
							$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="cod"]').show();
							$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="both"]').show();
							//$("#variationTable .single_variation_group_wrap:last").find('.abpdv_delhivery').val('');
							//calculate_courier_charge($("#variationTable .single_variation_group_wrap:last"));
						}
					}else if(technical_non_shipped_by=='' && (comp_id=='N' || comp_id=='')){
						$("#variationTable .single_variation_group_wrap:last").find(".shipped_by").val('');
						$("#variationTable .single_variation_group_wrap:last").find('.shipped_by option:not(:selected)').attr('disabled', false);
						$("#variationTable .single_variation_group_wrap:last").find(".abpdv_payment_method").val('');
						$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="cod"]').show();
						$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="both"]').show();
						//$("#variationTable .single_variation_group_wrap:last").find('.abpdv_delhivery').val('');
						//calculate_courier_charge($("#variationTable .single_variation_group_wrap:last"));
					}
				}else{
					$("#variationTable .single_variation_group_wrap:last").find(".shipped_by").val('');
					$("#variationTable .single_variation_group_wrap:last").find('.shipped_by option:not(:selected)').attr('disabled', false);
					$("#variationTable .single_variation_group_wrap:last").find(".abpdv_payment_method").val('');
					$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="cod"]').show();
					$("#variationTable .single_variation_group_wrap:last").find('.abpdv_payment_method option[value="both"]').show();
					//$("#variationTable .single_variation_group_wrap:last").find('.abpdv_delhivery').val('');
					//calculate_courier_charge($("#variationTable .single_variation_group_wrap:last"));
				}
			});
			$('body').on('click', '.single_variation_group_wrap thead tr', function(){
				$(this).parents('.single_variation_group_wrap').find('tbody').toggle('slow');
				if($(this).parents('.single_variation_group_wrap').hasClass('opened')){
					$(this).parents('.single_variation_group_wrap').addClass('closed');
					$(this).parents('.single_variation_group_wrap').removeClass('opened');
					$(this).find('.collaspe').text('+');
				}else{
					$(this).parents('.single_variation_group_wrap').addClass('opened');
					$(this).parents('.single_variation_group_wrap').removeClass('closed');
					$(this).find('.collaspe').text('-');
				}
			});
			/*** SHOW/HIDE TODAY OFFER DETAILS DIV ****/
			jQuery('body').on('change', '.abpd_today_offer', function(){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var abpd_today_offer = $(this).val();
				var todaydiscount = variationdiv.find('.abpd_today_discount').val();
				if(abpd_today_offer == 'Y'){
					$(".show_today_offer_active").removeClass('hidden');
				}else{
					$(".show_today_offer_active").addClass('hidden');
				}
				calculate_sale_price(variationdiv);
			});
			// variation status change event
			jQuery("body").on('change', ".abpdv_status", function(e){
			   var current = $(this);
			   var selected = $(this).val();
			   if(selected == 'N'){
			   	if($(this).closest("tr").find(".is_default_variation").is(":checked")){
						jAlert('Default variation is always Active!','Alert Dialog', function(){
							current.val('Y');
						});
			   	}else{
						$(this).closest("tr").find(".is_default_variation").prop('disabled', true);
						$(this).closest("tr").find("td:first-child label").attr('style', 'color: #b3b2b2;');
			   	}
			   }else{
			   	$(this).closest("tr").find(".is_default_variation").prop('disabled', false);
			   	$(this).closest("tr").find("td:first-child label").attr('style', '');
			   }
			});
			/****change quantity*****/
			jQuery("body").on('focusin', ".abpd_qty", function(e){
			    // console.log("Saving value " + $(this).val());
			    $(this).data('val', $(this).val());
			});
			jQuery("body").on('keyup change', ".abpd_qty", function(e){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var prev_qty = $(this).data('val');
				var abpdv_qty =$(this).val();
				var currentIndex = $(this).data('rowindex');
				var indexAbpdvQty = $("#abpd_qty_"+currentIndex).val();
				console.log('currentIndex: '+currentIndex+', indexAbpdvQty: '+indexAbpdvQty);
				if( indexAbpdvQty > 0 ) {
					var option = '';
					option += '<option value="">Select In Stock</option>';
					option += '<option value="Y" selected="" >Yes</option>';
					option += '<option value="N" >No</option>';
					// jQuery("#abpd_in_stock_"+currentIndex).val('Y'); // Set singel Yes
					// jQuery("#abpd_in_stock_"+currentIndex+" option[value=Y]").attr('selected', 'selected'); // Set singel Yes
					jQuery("#abpd_in_stock_"+currentIndex).empty();
					jQuery("#abpd_in_stock_"+currentIndex).html(option);
					jQuery("#abpd_in_stock_"+currentIndex).update();
					console.log('updated');
				}
				var sum = 0;
				var vatiation_chk = variationdiv.find('.is_default_variation:checked');
				$(".single_variation_group_wrap").each(function(){
					variation = $(this);
					if(variation.find(".abpdv_status").val() == 'Y'){
						var qty = parseInt(variation.find('.abpd_qty').val());
						sum += Number(qty);
					}
				});
				console.log(sum);
				var abpd_in_stock = 'N';
				if(sum > 0){
					if(abpdv_qty>0){
						abpd_in_stock = 'Y';
					}else{
						if($(vatiation_chk).prop('checked') == true ){
							alert("1111");
							jAlert('Please change default variant!','Alert Dialog');
							$(this).val(prev_qty);
							if(prev_qty > 0){
								abpd_in_stock = 'Y';
							}else{
								abpd_in_stock = 'N';
							}
							variationdiv.find('.abpd_in_stock').val(abpd_in_stock);
							return false;
						}
					}
				}
				variationdiv.find('.new_abpd_in_stock').val(abpd_in_stock);
				variationdiv.find('.abpd_in_stock').val(abpd_in_stock);
			});
			/** CHECK DISCOUNT OR TODAY DISCOUNT MUST NOT MORE THEN SALE PRICE ****/
			jQuery("body").on('keyup change', ".abpd_discount, .abpd_today_discount", function(e){
				var elem = $(this);
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var price = variationdiv.find('.abpd_price').val();
				var discount = variationdiv.find('.abpd_discount').val();
				var today_offer_avail = variationdiv.find('.abpd_today_offer').val();
				if(today_offer_avail == 'Y'){
					discount = variationdiv.find('.abpd_today_discount').val();
				}
				if(price == "" && discount != ''){
					jAlert('Please enter price first!','Alert Dialog', function(){
						elem.val('').focus();
						variationdiv.find('.abpd_sale_price').val('0.00');
					});
					return false;
				}
				if(today_offer_avail == 'Y'){		
					if(eval(price) < eval(discount)){
						jAlert('Please enter today discount less then price!','Alert Dialog', function(){
							variationdiv.find('.abpd_today_discount').val("").focus();
							calculate_sale_price(variationdiv);
						});
						return false;
					}
				}else{
					if(eval(price) < eval(discount)){
						jAlert('Please enter discount less then price!','Alert Dialog', function(){
							variationdiv.find('.abpd_discount').val("").focus();
						});
						return false;
					}
				}
			});
			/*** SHOW/HIDE LCOAL STORE DISCOUNT DETAILS DIV ****/
			jQuery('body').on("change", ".abpd_local_store_avail", function(){
				var variationdiv = $(this).parents(".single_variation_group_wrap");
				var local_store_avail = $(this).val();
				var localstorediscount = variationdiv.find('.abpd_local_store_dist').val();
				if(local_store_avail == 'Y' || local_store_avail == 'both'){
					variationdiv.find(".show_local_store_avail").removeClass('hidden');
					if(localstorediscount != '' && eval(localstorediscount) > 0){
						variationdiv.find(".abpd_local_store_dist").change();
					}
				}else{
					variationdiv.find(".show_local_store_avail").addClass('hidden');
				}
			});
			/*** CHECK LOCALSTORE DISCOUNT MUST NO MORE THEN SALE PRICE ***/
			jQuery("body").on('keyup change', ".abpd_local_store_dist", function(){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var price = variationdiv.find('.abpd_price').val();
				var saleprice = variationdiv.find('.abpd_sale_price').val();
				var localstorediscount = $(this).val();
				var local_store_avail = variationdiv.find('.abpd_local_store_avail').val();
				if(local_store_avail == 'Y'){
					if(price == ""){
						jAlert('Please enter price first!','Alert Dialog', function(){
							variationdiv.find('.abpd_local_store_dist').val("");
						});
						return false;
					}	
					if(eval(saleprice) < eval(localstorediscount)){
						jAlert('Please enter local store discount less then sale price!','Alert Dialog', function(){
							variationdiv.find('.abpd_local_store_dist').val("").focus();
						});
						return false;
					}
				}
			});
			/** CALCULATE SALE PRICE AND SELLER WILL GET AMOUNT BASED ON PRICE, DISCOUNT AND TODAY DISCOUNT **/
			jQuery('body'). on('keyup change', ".abpd_price, .abpd_discount, .abpd_today_discount, .shipped_by, .abpdv_payment_method", function(e){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				calculate_sale_price(variationdiv);
				calculate_courier_charge(variationdiv);
				calculate_mrp_discount(variationdiv);
				var rowIndex = $(this).data('rowindex');
				console.log('typeof: '+typeof(rowIndex));
				if( typeof(rowIndex) !== 'undefined' ) {
					setup_shiping_percentage(rowIndex);
				}
			});
			jQuery('body'). on('keyup change', ".abpd_post_charge", function(e){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				calculate_sale_price(variationdiv);
			});
			jQuery('.check_number').on('keydown',function(e){
				var val = jQuery(this).val();
				if (e.keyCode == 110 && val.indexOf('.') !== -1){
					e.preventDefault();
					return false;
				}
				if (jQuery.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 || (e.keyCode == 65 && e.ctrlKey === true) || (e.keyCode >= 35 && e.keyCode <= 39)) {
		             return;
					}
		    	if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
		        	e.preventDefault();
		    	}
			});
			// number of boxes is not set 0 start
			/*jQuery('body'). on('keyup change', ".abpdv_no_boxes", function(e){
				var box = $(this).val();
				if(!isNumber(box)){
					jAlert('Please enter number of box !', 'Alert Dialog', function(){
						$(this).find('.abpdv_no_boxes').focus();
					});
					return false;
				}
			});*/
			// number of boxes is not set 0 end
			jQuery('body'). on('keyup change', ".abpdv_post_charge_without_extra, .abpdv_post_charge_extra", function(e){
				var rowIndex = $(this).data('rowindex');
				setup_shiping_percentage(rowIndex);
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var abpdv_post_charge_without_extra = variationdiv.find('.abpdv_post_charge_without_extra').val();
				var abpdv_post_charge_extra = variationdiv.find('.abpdv_post_charge_extra').val();
				var abpd_post_charge = parseFloat(abpdv_post_charge_without_extra) + parseFloat(abpdv_post_charge_extra);
				abpd_post_charge = parseFloat(abpd_post_charge).toFixed(2);
				//if(!seller_id_arr.includes(seller_id)){
					variationdiv.find('.abpd_post_charge').val(abpd_post_charge);
				//}
				calculate_sale_price(variationdiv);
			});
			$("#prdct_frm").on('keydown', "input", function(e){
				if(e.keyCode == 13) {
					$("#prdct_frm").submit();
					e.preventDefault();
					return false;
				}
			});
			/** CALCULATE PRODUCT VOLUMETRIC WEIGHT ***/
			jQuery("body").on('keyup change', ".abpd_length, .abpd_width, .abpd_height", function(){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var abpd_length = variationdiv.find(".abpd_length").val();
				var abpd_width = variationdiv.find(".abpd_width").val();
				var abpd_height = variationdiv.find(".abpd_height").val();
				var abpd_volumetric_weight = ((abpd_length*abpd_width*abpd_height)/5000).toFixed(2);
				variationdiv.find(".abpd_volumetric_weight").val(abpd_volumetric_weight);
				calculate_courier_charge(variationdiv);
				calculate_sale_price(variationdiv);
			});
			jQuery("body").on('keyup change', ".abpdv_seller_id",function(){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				calculate_sale_price(variationdiv);
			});
			/****CALCULATE COURIER FEE ****/
			jQuery("body").on('keyup change', ".abpd_weight_packing", function(){
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var comp_id = jQuery("#abpd_comp_id").find(':selected').attr('data-is_branded');
				var technical_shipped_by = jQuery("#abpd_technical_id").find(':selected').attr('data-technical_shipped_by');
				if(jQuery.type(technical_shipped_by) === "undefined"){
	    			technical_shipped_by = '';
				}
				var shipped_by = jQuery(variationdiv).find('.shipped_by').val();
				shipping_commission = '';
				var shipped_by_commissionJSON = <?php echo json_encode($agribegriCommisson_Arr, JSON_PRETTY_PRINT); ?>;
		    	jQuery.each(shipped_by_commissionJSON, function(shippedBy, commission_per) {
				    if(shipped_by == shippedBy){
				    	shipping_commission = commission_per;
				    }
				});
				var check_weight_packing = jQuery(variationdiv).find(".abpd_weight_packing").val();
				if((technical_shipped_by!='' || comp_id=='Y') ){
					if(check_weight_packing <=5000 && shipping_commission <= 15){
						jQuery(variationdiv).find('.abpdv_delhivery').prop('checked', true);
					}else{
						jQuery(variationdiv).find('.abpdv_delhivery').prop('checked', false);
					}
				}else{
					jQuery(variationdiv).find('.abpdv_delhivery').prop('checked', false);
				}
				calculate_courier_charge(variationdiv);
				calculate_fulfilment_charge();
			});
			jQuery("body").on('keyup change', ".abpdv_shipping_through", function(){
				calculate_fulfilment_charge();
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				calculate_courier_charge(variationdiv);
			});
			jQuery('body').on('change',".abpdv_delhivery",function(){
				//if(jQuery(this).prop('checked') == true){
					var variationdiv = $(this).parents('.single_variation_group_wrap');
					calculate_courier_charge(variationdiv);
				//}
			});
			/****CALCULATE COURIER FEE ****/
			/****CALCULATE Delhivery Charge ****/
			/****CALCULATE Delhivery Charge ****/
			jQuery('body').on('change', ".is_default_variation", function(){
				var default_variation_len = $(".is_default_variation:checked").length;
				var default_vatiation_chk = $(this);
				if($(this).prop('checked') == true && default_variation_len > 1){
					jAlert('Default variation already assigned', 'Alert Dialog', function(){
						default_vatiation_chk.prop('checked', false);
					});
				}
			});
			jQuery('body').on('keyup change', ".abpd_unit, .abpd_unit_quantity", function(){
				// update_variation_code();
				var prod_code = jQuery('#abpd_code').val();
				var variationdiv = $(this).parents('.single_variation_group_wrap');
				var unit = variationdiv.find('.abpd_unit').val();
				var unit_qty = variationdiv.find('.abpd_unit_quantity').val();
				var count = variationdiv.find('.var_count').val();
				// console.log(count);
				jQuery.ajax({
					url:'add_prod_variation.php',
					data:{prod_code:prod_code,unit:unit,unit_qty:unit_qty,count:count,type:'generate_variation_code'},
					type:'POST',
					success:function(response){
						variationdiv.find('.abpdv_code').val(response);
					}
				});
				// console.log(prod_code+'-'+unit+'-'+unit_qty+'-'+count);
			});
			$("#btn_frm_submit").on('click', function(){
				var prod_id = jQuery("#abpd_id").val();
				jQuery('#similar_prod_table').dataTable().fnFilter('');
            	jQuery('#same_prod_table').dataTable().fnFilter('');
				jQuery('#recomandedn_table').dataTable().fnFilter('');
				var ext = '';
				if(jQuery('#abpd_guildline_doc')[0].files.length > 0){
					var abpd_guildline_doc= jQuery('#abpd_guildline_doc').val();
		   		var ext = abpd_guildline_doc.split('.').pop().toLowerCase();
		   		var size = Math.round(jQuery('#abpd_guildline_doc')[0].files[0].size / 1024);
		   		//alert(size);
		   		//var size = abpd_guildline_doc.
			   }
			   var old_default_img = jQuery("#abpd_old_default_img").val();
			   var default_img_ext = '';
				if(jQuery('#abpd_default_img')[0].files.length > 0){
					var abpd_default_img= jQuery('#abpd_default_img').val();
		   		var default_img_ext = abpd_default_img.split('.').pop().toLowerCase();
		   		var default_img_size = jQuery('#abpd_default_img')[0].files[0].size;
			   }
			   /*if(jQuery('#abpd_meta_keyword').val()==""){
					jAlert('Please Enter meta keywords!','Alert Dialog');
					$("#abpd_meta_keyword").focus();
					return false;
				}*/
				if(jQuery('#abpd_meta_desc').val()==""){
					jAlert('Please Enter meta description!','Alert Dialog');
					$("#abpd_meta_desc").focus();
					return false;
				}
				if(prod_id > 0 && jQuery("#abpd_active").val()=='D'){
					return true;
				}else{
					var ck1 =  CKEDITOR.instances.abpd_descri.getData();
					var keyCode = $('#abpd_name').val();
					var regex = /^[A-Za-z0-9\s.!&+()%^,:';-]+$/;
 					//alert(keyCode);
					//Validate TextBox value against the Regex.
					var isValid = regex.test((keyCode));
					// product tag required
					var product_tags = $('#abpd_product_tags_tagsinput .tag').length;
					var abpd_userid = $("#abpd_userid").val();
					if(product_tags == 0){
						jAlert('Please Enter Product Tags!','Alert Dialog');
						return false;
					}/*else if((eval(product_tags)<25) && (abpd_userid!=1 || abpd_userid!=20)){
						jAlert('Please Enter Minimum 25 Product Tags!','Alert Dialog');
						return false;
					}*/
					// default variation set start
					var sum = 0;
					$(".single_variation_group_wrap").each(function(){
						variation = $(this);
						if(variation.find(".abpdv_status").val() == 'Y'){
							var qty = parseInt(variation.find('.abpd_qty').val());
							sum += Number(qty);
						}
					});
					// console.log("Sum = "+sum);
					// default variation set end
					if($('#abpd_comp_id').val()==""){
						jAlert('Please Select Company!','Alert Dialog',);
						return false;
					}else if($('#abpd_absupplier_id').val()==""){
						jAlert('Please Select Seller!','Alert Dialog');
						return false;		
					}else if($('#abpd_cat_id').val()==""){
						jAlert('Please Select Category!','Alert Dialog');
						return false;		
					}else if($('#abpd_name').val()==""){
						jAlert('Please Enter Product Name!','Alert Dialog');
						return false;
					}/*else if($('#abpd_name').val()!="" && $('#abpd_name').val().length>150 || $('#abpd_name').val().length<75){
						jAlert('Product name must be of minimum 75 characters and maximum 150 characters.','Alert Dialog');
						return false;
					}*/else if(jQuery('#pnmae_exist').val()=="Yes"){
						jAlert('Product Already Exist!','Alert Dialog');
						return false;
					}else if (!isValid) {
						jAlert('Special characters not allowed!','Alert Dialog');
						$('#abpd_name').focus();
						return false;
					}else if(ck1==""){
						jAlert('Please Enter Description!','Alert Dialog');
						return false;
					}else if(jQuery('#abpd_gst_no').val()==""){
						jAlert('Please enter GST price!','Alert Dialog');
						return false;
					}else if(jQuery('#abpd_meta_title').val()==""){
						jAlert('Please Enter meta title!','Alert Dialog');
						return false;
					}else if(ext!="pdf" && ext!=''){ 	
						jAlert('Please upload PDF file only for guideline document!','Alert Dialog');
						return false;
					}else if(ext!='' && size>1024){
						jAlert('Please upload less than 1MB file for guideline document!','Alert Dialog');
						return false;
					}else if(old_default_img=='' && default_img_ext==''){
						jAlert('Please Upload Default Image!','Alert Dialog');
						return false;
					}else if(default_img_ext!='' && jQuery.inArray(default_img_ext, ['png','jpg','jpeg','webp']) == -1){ 	
						jAlert('Please upload only image file (.jpg, .png, .jpeg) for Default Image!','Alert Dialog');
						return false;
					}else if(default_img_ext!='' && default_img_size>100000){
						jAlert('Please upload maximum 100KB image for Default Image!','Alert Dialog');
						return false;
					}/*else if(jQuery('#image_imgdigital').val()==""){
						jAlert('Please Select Product Image!','Alert Dialog');
						return false;			
					}*/else{
						var validate_variation = true;
						var variation_index = 1;
						var tmp_stock_check=0;
						$(".single_variation_group_wrap").each(function(){
							variation = $(this);
							var price = variation.find('.abpd_price').val();
							var discount = variation.find('.abpd_discount').val();
							var today_offer_avail = variation.find('.abpd_today_offer').val();
							var todaydiscount = variation.find('.abpd_today_discount').val();
							var local_store_avail = variation.find('.abpd_local_store_avail').val();
							var local_store_dist = variation.find('.abpd_local_store_dist').val();
							var abpdv_post_charge_without_extra = variation.find('.abpdv_post_charge_without_extra').val();
							var abpdv_post_charge_extra = variation.find('.abpdv_post_charge_extra').val();
							var abpdv_shipping_through = variation.find('.abpdv_shipping_through').val();
							var shipped_by = variation.find('.shipped_by').val();
							var abpd_qty = variation.find('.abpd_qty').val();
							var is_default_var = variation.find('.is_default_variation:checked').length;
							//console.log("Shp By : " + shipped_by.indexOf('Self'));		
							//if(sum > 0){
								if(is_default_var == 1){
									if((abpd_qty == '0.00') || (abpd_qty == '0')){
										tmp_stock_check=1;
										/*jConfirm('Default variation is out of stock, Are you still want to Save the data?', 'Alert Dialog', function (ans) {
						               if (ans) {
						               	validate_variation = true;
						                  return true;
						               }else{
						               	variation.find('.abpd_qty').focus();
												validate_variation = false;
												return false;
						               }
						            });*/
										/*jAlert('You can set default variation which quantity is greater than 0!','Alert Dialog');
										variation.find('.abpd_qty').focus();
										validate_variation = false;
										return false;*/
									}
								}
							//}
							if(today_offer_avail == 'Y'){
								salepricedist = todaydiscount;
							}else{
								salepricedist = discount;
							}
							var saleprice = eval(price) - eval(salepricedist);
							if(variation.find('.abpd_unit').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please select unit!', 'Alert Dialog', function(){
									variation.find('.abpd_unit').focus();
								});
								validate_variation = false;							
								return false;
							}else if(variation.find('.abpd_unit_quantity').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter unit quantity!', 'Alert Dialog', function(){
									variation.find('.abpd_unit_quantity').focus();
								});								
								validate_variation = false;
								return false;
							}else if(variation.find('.abpd_price').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter price!', 'Alert Dialog', function(){
									variation.find('.abpd_price').focus();
								});
								validate_variation = false;
								return false;
							}else if(discount != '' && eval(discount) > eval(price)){
								open_variation_group_for_validation(variation);
								jAlert('Please enter discount less then price!', 'Alert Dialog', function(){
									variation.find('.abpd_discount').val('').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpdv_shipping_through').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please select shipping through!', 'Alert Dialog', function(){
									variation.find('.abpdv_shipping_through').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpdv_payment_method').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please select payment method!', 'Alert Dialog', function(){
									variation.find('.abpdv_payment_method').focus();
								});
								validate_variation = false;
								return false;
							}else if(today_offer_avail == 'Y' && todaydiscount != '' && eval(todaydiscount) > eval(price) ){
								open_variation_group_for_validation(variation);
								jAlert('Please enter today discount less then price!', 'Alert Dialog', function(){
									variation.find('.abpd_today_discount').val('').focus();
									calculate_sale_price(variation);
								});
								validate_variation = false;
								return false;
							}else if((local_store_avail == 'Y' || local_store_avail == 'both') && (local_store_dist == '' || local_store_dist=='0.00')){
								open_variation_group_for_validation(variation);
								jAlert('Please enter local store discount!', 'Alert Dialog', function(){
									variation.find('.abpd_local_store_dist').val('').focus();
								});
								validate_variation = false;
								return false;
							}else if(local_store_avail == 'Y' && local_store_dist != '' && eval(local_store_dist) > eval(saleprice) ){
								open_variation_group_for_validation(variation);
								jAlert('Please enter local store discount less then sale price!', 'Alert Dialog', function(){
									variation.find('.abpd_local_store_dist').val('').focus();
									calculate_sale_price(variation);
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpd_qty').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter stock quantity!', 'Alert Dialog', function(){
									variation.find('.abpd_qty').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpd_weight_packing').val() == '' || variation.find('.abpd_weight_packing').val() == '0.00'){
								open_variation_group_for_validation(variation);
								jAlert('Please enter product weight with packing!', 'Alert Dialog', function(){
									variation.find('.abpd_weight_packing').focus();
								});
								validate_variation = false;
								return false;
							}else if(eval(variation.find('.abpd_weight_packing').val()) <= '50'){
								open_variation_group_for_validation(variation);
								jAlert('Please enter product weight with packing more than 50 gram.', 'Alert Dialog', function(){
									variation.find('.abpd_weight_packing').focus();
								});
								validate_variation = false;
								return false;
							}else if(abpdv_shipping_through!='Shipping Through Transport' && shipped_by.indexOf('Self')!=0 && (abpdv_post_charge_without_extra == '0.00' || abpdv_post_charge_without_extra == '')){
								open_variation_group_for_validation(variation);
								jAlert('Please enter courier fee without extra charge!', 'Alert Dialog', function(){
									variation.find('.abpdv_post_charge_without_extra').focus();
								});
								validate_variation = false;
								return false;
							}else if(abpdv_shipping_through!='Shipping Through Transport' && shipped_by.indexOf('Self')!=0 && (abpdv_post_charge_extra == '0.00' || abpdv_post_charge_extra == '')){
								open_variation_group_for_validation(variation);
								jAlert('Please enter courier fee extra charge!', 'Alert Dialog', function(){
									variation.find('.abpdv_post_charge_extra').focus();
								});
								validate_variation = false;
								return false;
							/*}else if(courier_fee != '' && eval(price) < eval(courier_fee)){
								jAlert('Please enter courier fee less then MRP of product!','Alert Dialog', function(){
									variation.find('.abpd_post_charge').val("").focus();
								});
								validate_variation = false;
								return false;*/
							}else if(variation.find('.abpd_in_stock').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please select in stock!', 'Alert Dialog', function(){
									variation.find('.abpd_in_stock').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpd_min_order_qty').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter minimum order quantity!', 'Alert Dialog', function(){
									variation.find('.abpd_min_order_qty').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.shipped_by').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please select shipped by!', 'Alert Dialog', function(){
									variation.find('.shipped_by').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpd_length').val() == '' || variation.find('.abpd_length').val() == '0.00'){
								open_variation_group_for_validation(variation);
								jAlert('Please enter length!', 'Alert Dialog', function(){
									variation.find('.abpd_length').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpd_width').val() == '' || variation.find('.abpd_width').val() == '0.00'){
								open_variation_group_for_validation(variation);
								jAlert('Please enter width!', 'Alert Dialog', function(){
									variation.find('.abpd_width').focus();
								});
								validate_variation = false;
								return false;
							}
							else if(variation.find('.abpd_height').val() == '' || variation.find('.abpd_height').val() == '0.00'){
								open_variation_group_for_validation(variation);
								jAlert('Please enter height!', 'Alert Dialog', function(){
									variation.find('.abpd_height').focus();
								});
								validate_variation = false;
								return false;
							}
							else if(variation.find('.abpdv_display_order').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter display order!', 'Alert Dialog', function(){
									variation.find('.abpdv_display_order').focus();
								});
								validate_variation = false;
								return false;
							}
							else if(variation.find('.abpdv_notes').val() != '' && variation.find('.abpdv_notes').val().length>45){
								open_variation_group_for_validation(variation);
								jAlert('Please enter only 45 characters for notes!', 'Alert Dialog', function(){
									variation.find('.abpdv_notes').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpdv_no_boxes').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter number of box !', 'Alert Dialog', function(){
									variation.find('.abpdv_no_boxes').focus();
								});
								validate_variation = false;
								return false;
							}else if(variation.find('.abpdv_no_boxes').val() == 0){
								open_variation_group_for_validation(variation);
								jAlert('Please enter more then 0 boxes!', 'Alert Dialog', function(){
									variation.find('.abpdv_no_boxes').focus();
								});
								validate_variation = false;
								return false;
							}
							variation_index++;
						}); /** end of variation validation **/
						/** if all fields of variations are valid then check for default variation set or not **/
						if(validate_variation){
							var default_variation_len = $(".is_default_variation:checked").length;
							var variation_status_len = $('.abpdv_status option[value="Y"]:selected').length;
							if(variation_status_len == 0){
								jAlert('Variation Active Status is needed, Please assign any one!', 'Alert Dialog');
								validate_variation = false;
							}else if(default_variation_len == 0){
								jAlert('Default variation is needed, Please assign any one!', 'Alert Dialog');
								validate_variation = false;
							}else if(default_variation_len > 1){
								jAlert('Only one variation can be assigned as default!', 'Alert Dialog');
								validate_variation = false;
							} 
						}
						if(tmp_stock_check)//START OF CONFIRM
						{
							var chek_alert = $("#glob_con_status").val();
							if(chek_alert==0)
							{
								jConfirm('Default variation is out of stock, Are you still want to Save the data?', 'Alert Dialog', function (ans) {	
				               if (ans) {
				               	validate_variation = true;
				               	$("#glob_con_status").val(1);
										$("#btn_frm_n_submit").trigger('click');
				               }else{
										validate_variation = false;
										$("#glob_con_status").val(0);
				               }
				            });
				            validate_variation = false;
							}else{
								validate_variation = true;
							}
						}
						console.log(validate_variation);
						if(validate_variation){
							$("#btn_frm_n_submit").trigger('click');
						}
						//return validate_variation;					
					} /** end of all fields validation **/
			  } /** end of product active inactive condition **/
			});
			$("#btn_save_draft").on('click',function(){
				var prod_id = jQuery("#abpd_id").val();
				jQuery('#similar_prod_table').dataTable().fnFilter('');
            	jQuery('#same_prod_table').dataTable().fnFilter('');
				jQuery('#recomandedn_table').dataTable().fnFilter('');
				var ext = '';
				if(jQuery('#abpd_guildline_doc')[0].files.length > 0){
					var abpd_guildline_doc= jQuery('#abpd_guildline_doc').val();
		   		var ext = abpd_guildline_doc.split('.').pop().toLowerCase();
		   		var size = Math.round(jQuery('#abpd_guildline_doc')[0].files[0].size / 1024);
			   }
			   var old_default_img = jQuery("#abpd_old_default_img").val();
			   var default_img_ext = '';
				if(jQuery('#abpd_default_img')[0].files.length > 0){
					var abpd_default_img= jQuery('#abpd_default_img').val();
		   		var default_img_ext = abpd_default_img.split('.').pop().toLowerCase();
		   		var default_img_size = jQuery('#abpd_default_img')[0].files[0].size;
			   }
				if(jQuery('#abpd_meta_desc').val()==""){
					jAlert('Please Enter meta description!','Alert Dialog');
					$("#abpd_meta_desc").focus();
					return false;
				}
				if(prod_id > 0 && jQuery("#abpd_active").val()=='D' && jQuery("#abpd_inactive_reason").val()!=''){
					return true;
				}else{
					var ck1 =  CKEDITOR.instances.abpd_descri.getData();
					var keyCode = $('#abpd_name').val();
					var regex = /^[A-Za-z0-9\s.!&+()%^,:';-]+$/;
					var isValid = regex.test((keyCode));
					// product tag required
					var product_tags = $('#abpd_product_tags_tagsinput .tag').length;
					console.log("T : " + product_tags);
					if(product_tags == 0){
						jAlert('Please Enter Product Tags!','Alert Dialog');
						return false;
					}
					// default variation set start
					var sum = 0;
					$(".single_variation_group_wrap").each(function(){
						variation = $(this);
						if(variation.find(".abpdv_status").val() == 'Y'){
							var qty = parseInt(variation.find('.abpd_qty').val());
							sum += Number(qty);
						}
					});
					// default variation set end
					if($("#abpd_active").val()=='D' && $("#abpd_inactive_reason").val()==''){
						jAlert('Please Select Inactive Reason!','Alert Dialog');
						$("#abpd_inactive_reason").focus();
						return false;
					}else if($('#abpd_comp_id').val()==""){
						jAlert('Please Select Company!','Alert Dialog',);
						return false;
					}else if($('#abpd_absupplier_id').val()==""){
						jAlert('Please Select Seller!','Alert Dialog',);
						return false;
					}else if($('#abpd_cat_id').val()==""){
						jAlert('Please Select Category!','Alert Dialog');
						return false;		
					}else if($('#abpd_name').val()==""){
						jAlert('Please Enter Product Name!','Alert Dialog');
						return false;
					}else if($('#abpd_name').val()!="" && $('#abpd_name').val().length>150 || $('#abpd_name').val().length<75){
						jAlert('Product name must be of minimum 75 characters and maximum 150 characters.','Alert Dialog');
						return false;
					}else if(jQuery('#pnmae_exist').val()=="Yes"){
						jAlert('Product Already Exist!','Alert Dialog');
						return false;
					}else if (!isValid) {
						jAlert('Special characters not allowed!','Alert Dialog');
						$('#abpd_name').focus();
						return false;
					}else if(ck1==""){
						jAlert('Please Enter Description!','Alert Dialog');
						return false;
					}else if(jQuery('#abpd_gst_no').val()==""){
						jAlert('Please enter GST price!','Alert Dialog');
						return false;
					}/*else if(jQuery('#abpd_hsn_code').val()==""){
						jAlert('Please enter HSN Code!','Alert Dialog');
						return false;
					}*/else if(jQuery('#abpd_meta_title').val()==""){
						jAlert('Please Enter meta title!','Alert Dialog');
						return false;
					}else if(ext!="pdf" && ext!=''){ 	
						jAlert('Please upload PDF file only for guideline document!','Alert Dialog');
						return false;
					}else if(ext!='' && size>1024){
						jAlert('Please upload less than 1MB file for guideline document!','Alert Dialog');
						return false;
					}else if(old_default_img=='' && default_img_ext==''){
						jAlert('Please Upload Default Image!','Alert Dialog');
						return false;
					}else if(default_img_ext!='' && jQuery.inArray(default_img_ext, ['png','jpg','jpeg']) == -1){ 	
						jAlert('Please upload only image file (.jpg, .png, .jpeg) for Default Image!','Alert Dialog');
						return false;
					}else if(default_img_ext!='' && default_img_size>100000){
						jAlert('Please upload maximum 100KB image for Default Image!','Alert Dialog');
						return false;
					}/*else if(jQuery('#image_imgdigital').val()==""){
						jAlert('Please Select Product Image!','Alert Dialog');
						return false;			
					}*/else{
						var validate_variation = true;
						var variation_index = 1;
						var tmp_stock_check=0;
						$(".single_variation_group_wrap").each(function(){
							variation = $(this);
							var price = variation.find('.abpd_price').val();
							var discount = variation.find('.abpd_discount').val();
							var today_offer_avail = variation.find('.abpd_today_offer').val();
							var todaydiscount = variation.find('.abpd_today_discount').val();
							var local_store_avail = variation.find('.abpd_local_store_avail').val();
							var local_store_dist = variation.find('.abpd_local_store_dist').val();
							var abpdv_post_charge_without_extra = variation.find('.abpdv_post_charge_without_extra').val();
							var abpdv_post_charge_extra = variation.find('.abpdv_post_charge_extra').val();
							var abpdv_shipping_through = variation.find('.abpdv_shipping_through').val();
							var abpd_qty = variation.find('.abpd_qty').val();
							var is_default_var = variation.find('.is_default_variation:checked').length;
							//if(sum > 0){
								if(is_default_var == 1){
									if((abpd_qty == '0.00') || (abpd_qty == '0')){
										tmp_stock_check=1;
										/*var conf = jConfirm('You can set default variation which quantity is greater than 0!','Alert Dialog');
										console.log(conf);
										variation.find('.abpd_qty').focus();
										validate_variation = false;
										return false;*/
									}
								}
							//}
							if(today_offer_avail == 'Y'){
								salepricedist = todaydiscount;
							}else{
								salepricedist = discount;
							}
							var saleprice = eval(price) - eval(salepricedist);
							if(variation.find('.abpd_unit').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please select unit!', 'Alert Dialog', function(){
									variation.find('.abpd_unit').focus();
								});
								validate_variation = false;							
								return false;
							}else if(variation.find('.abpd_unit_quantity').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter unit quantity!', 'Alert Dialog', function(){
									variation.find('.abpd_unit_quantity').focus();
								});								
								validate_variation = false;
								return false;
							}else if(variation.find('.abpd_price').val() == ''){
								open_variation_group_for_validation(variation);
								jAlert('Please enter price!', 'Alert Dialog', function(){
									variation.find('.abpd_price').focus();
								});
								validate_variation = false;
								return false;
							}else if(discount != '' && eval(discount) > eval(price)){
								open_variation_group_for_validation(variation);
								jAlert('Please enter discount less then price!', 'Alert Dialog', function(){
									variation.find('.abpd_discount').val('').focus();
								});
								validate_variation = false;
								return false;
							}
							variation_index++;
						}); /** end of variation validation **/
						/** if all fields of variations are valid then check for default variation set or not **/
						if(validate_variation){
							var default_variation_len = $(".is_default_variation:checked").length;
							if(default_variation_len == 0){
								jAlert('Default variation is needed, Please assign any one!', 'Alert Dialog');
								validate_variation = false;
							}else if(default_variation_len > 1){
								jAlert('Only one variation can be assigned as default!', 'Alert Dialog');
								validate_variation = false;
							}
						}
						if(validate_variation){
							jQuery("#btn_save_n_draft").trigger('click');
						}				
					} /** end of all fields validation **/
			  } /** end of product active inactive condition **/
			});
			if($('.single_variation_group_wrap').length > 1){
				reset_add_remove_btn();
			}
			$(document).on("keypress", ".only-numeric", function (e) {
				console.log('keywork: '+keyCode);
				var keyCode = e.which ? e.which : e.keyCode
				if ( !(keyCode >= 46 && keyCode <= 57) ) {
					return false;
				}
			});	
			function setup_shiping_percentage(rowIndex){
				//console.log($('#abpdv_post_charge_without_extra_'+rowIndex).val())
				//console.log("rowIndex: "+rowIndex);
				var tmpPostChargeWithoutExtraVal = parseFloat($('#abpdv_post_charge_without_extra_'+rowIndex).val());
				var tmpPostChargeExtraVal = parseFloat($('#abpdv_post_charge_extra_'+rowIndex).val());
				var tmpSalesPriceVal = parseFloat($('#abpdv_sale_price_'+rowIndex).val());
				//console.log(tmpPostChargeWithoutExtraVal+'--'+tmpPostChargeExtraVal+'--'+tmpSalesPriceVal);
				var tmpPostChargeWithoutExtra = ( tmpPostChargeWithoutExtraVal > 0 ) ? tmpPostChargeWithoutExtraVal : 0;
				var tmpPostChargeExtra = ( tmpPostChargeExtraVal > 0 ) ? tmpPostChargeExtraVal : 0;
				var tmpSalesPrice = ( tmpSalesPriceVal > 0 ) ? tmpSalesPriceVal : 0;
				 //console.log('tmpPostChargeWithoutExtra: '+tmpPostChargeWithoutExtra); // 	1502
				 //console.log('tmpPostChargeExtra: '+tmpPostChargeExtra); // 340
				 //console.log('tmpSalesPrice: '+tmpSalesPrice); // 17000
				if( tmpSalesPrice > 0 ) {
					var tmpshippingcharge = parseFloat( ( (tmpPostChargeWithoutExtra + tmpPostChargeExtra) * 100 ) / tmpSalesPrice).toFixed(2);
				} else {
					tmpshippingcharge = parseInt(0).toFixed(2);
				}
				// console.log("rowIndex: "+rowIndex+", tmpshippingcharge: "+tmpshippingcharge);
				$("#vairant_shipping_per_"+rowIndex).empty();
				$("#vairant_shipping_per_"+rowIndex).html(tmpshippingcharge);
			}
		});
		jQuery("#submit_state_campaign").on('click',function(e){
			var state = jQuery("#abpd_state_id").val();
			var month = jQuery("#abpd_state_compaign_month").val();
			var product_id = jQuery("#product_id").val();
			if(state==null){
				jAlert('Please Select State!', 'Alert Dialog');
				return false;
			}else if(month==null){
				jAlert('Please Select Month!', 'Alert Dialog');
				return false;
			}else{
				jQuery.ajax({
					url:'add_prod_variation.php',
					data:{state:state,month:month,product_id:product_id,type:'add_state_compaign'},
					type:'POST',
					dataType: 'json',
					success:function(response){
						jQuery("#state_compaign_body").empty();
						jQuery("#state_compaign_body").html(response.html);
						jQuery("#abpd_state_id").val('');
						jQuery("#abpd_state_id").multiselect('destroy');
						jQuery('#abpd_state_id').multiselect({ 
							includeSelectAllOption: true,
							enableFiltering: true,
							enableCaseInsensitiveFiltering: true,
							maxHeight: 170 ,
							nonSelectedText:'Select State',
							numberDisplayed: 0,       
						});
						jQuery("#abpd_state_compaign_month").val('');
						jQuery("#abpd_state_compaign_month").multiselect('destroy');
						jQuery('#abpd_state_compaign_month').multiselect({ 
							includeSelectAllOption: true,
							enableFiltering: true,
							enableCaseInsensitiveFiltering: true,
							maxHeight: 170 ,
							nonSelectedText:'Select Month',
							numberDisplayed: 0,       
						});
					}	
				});
			}
		});
	function ConfirmDelete(id){
		jConfirm('Are you sure you want to delete this Record?','Confirm Dialog',function(r){
			if(r){
				jQuery.ajax({
					type:"POST",
					url:"add_prod_variation.php",
					async:false,
					data:{sc_id:id,type:'confirm_delete'},
					success:function(data){
					  if(data.trim()=='confirm_deleted'){
						  jQuery('#statecompaign_'+id).fadeOut('slow');
						  jAlert('Record Deleted Successfully!','Alert Dialog');
					  }
					}
				});
			}
		});
	}
	function change_order_no(order_no,product_img_id,order_table_name){
		if(order_no!='' && product_img_id!=''){
			jQuery.ajax({
				url:'add_prod_variation.php',
				data:{order_no:order_no,product_img_id:product_img_id,order_table_name:order_table_name,type:'change_img_order_no'},
				type:'POST',
				dataType: 'json',
				success:function(response){
					if(response.error==0){
						jQuery("#order_no_"+product_img_id).val(response.order_no);
					}
				}	
			});
		}
	}
	</script>
</body>
</html>		
