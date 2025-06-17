<?php
include_once("Include/activity_log.php"); 
include('internalaccess/session.php');
include('internalaccess/connectdb.php');
include('Include/functions.php');
$M_Menu_o4 = 'active';
$M_Menu_o4_dd = 'block';
$P_Menu_o2 = 'active';
if($access_array['aba_access_company']['abaac_view']!='Yes'){ 
	header('location:dashboard.php');
}
function fileuploadimg($old_image, $imgfile, $folder=''){
	//echo $old_image.'--'.$imgfile; exit;
	if (isset($imgfile) && $imgfile!= '') {
		$date = date("m-d-y-H-i-s");
		$uploadfileNameArr = explode(".", $imgfile);
		$filename = md5("image" . rand(999,9999)) . "-" . $date .'.'. $uploadfileNameArr[count($uploadfileNameArr)-1];
		$upload_to_temp = "temp_img/" . $imgfile;				
		$ftp_path = '../images/'.trim($folder)."/".$filename;
		$ret_path = trim($folder)."/".$filename;
		//echo $upload_to_temp;
		//echo $old_image.'--'.$upload_to_temp; exit; 
		if (file_exists($upload_to_temp)) {	
			//echo "sdffffff";  exit;
			if(copy('temp_img/'.$imgfile,$ftp_path)){
				//echo "testing"; 
				unlink($upload_to_temp);
				$image_up = $ftp_path;
				if ($old_image != '') {

					unlink($old_image);
				}
				return $ret_path;
			}else{
				return '';
			} 
		}else{
			return '';
		}
	} //exit;
	return '';
}
$banner_url = "upload_image_popup_company_banner.php?table=ab_company&field=abc_banner_image&id_field=abc_id";
if(isset($_POST) && $_POST['type']=='delete'){
	$abc_id = $_POST['abc_id'];
	if($abc_id>0){
		$del_img_name = "SELECT abc_image,abc_banner_image FROM ab_company WHERE abc_id = '".$_POST['abc_id']."'";
		$del_img_row = mysqlFetchAssoc(mysqlQuery($del_img_name));
		
		$delete = mysqlQuery("DELETE FROM ab_company WHERE abc_id = '".$_POST['abc_id']."'");
		if($delete){
			unlink('images/'.$del_img_row['abc_image']);
        	unlink('../images/' . $del_img_row['abc_banner_image']);
			$msg = '';
		}else{
			$msg = 'cant';
		}
		
	}
	echo $msg;
	exit();
}
if(isset($_POST) && $_POST['type']  == 'get_show_on_home_count'){
	$showhomeQry = "SELECT COUNT(abc_id) as top_company_count FROM ab_company WHERE abc_top_company = 'yes'";
	$showhomeRes = mysqlQuery($showhomeQry);
	$showhomeRow = mysqlFetchArray($showhomeRes);
	if($showhomeRow['top_company_count'] < $MAXIMUM_TOP_COMPANIES_COUNT){
		echo 'yes';
	}else{
		echo 'no';
	}
	exit;
}

if(isset($_POST) && $_POST['type']=='UPDATE_ORDER'){

	$company_id = mysqlRealescapestring($_POST['company_id']);
	$display_order = mysqlRealescapestring($_POST['display_order']);
	
	$query = "UPDATE ab_company SET
			  abc_display_order = '".$display_order."'
			  WHERE abc_id = '".$company_id."'";
			  $msg = 'up';
	$res = mysqlQuery($query);
	if($res)
	{
		echo "up";
	}
	
	exit();	
}

if(isset($_POST) && $_POST['type']=='UPDATE_FRONT'){

	$company_id = mysqlRealescapestring($_POST['company_id']);
	$display_front = mysqlRealescapestring($_POST['display_front']);
	
	$query = "UPDATE ab_company SET
			  abc_display_front = '".$display_front."'
			  WHERE abc_id = '".$company_id."'";
			  $msg = 'up';
	$res = mysqlQuery($query);
	if($res)
	{
		echo "up";
	}
	
	exit();	
}



if(isset($_POST) && $_POST['type']=='check'){
$abc_id = $_POST['abc_id'];
$strwhere = '';
if($abc_id>0){
	$strwhere = " AND abc_id<>'".$abc_id."'";
}	
	$ab_sel = mysqlQuery("select abc_id,abc_name,abc_name_hindi,abc_active from ab_company where  abc_name='".$_POST['abc_name']."' $strwhere");
	$ab_row = mysqlNumRow($ab_sel);
	
	if($ab_row>0){
		echo 'Yes';
	}else{
		echo 'No';
	}
	exit();
}	


if(isset($_POST) && $_POST['submit']=='Submit'){
	
	
	$abc_active = mysqlRealescapestring($_POST['abc_active']);
	$abc_name = mysqlRealescapestring($_POST['abc_name']);
	/*$abc_name_hindi = mysqlRealescapestring($_POST['abc_name_hindi']);*/
	$abc_desc = mysqlRealescapestring($_POST['abc_desc']);
	$abc_meta_title = mysqlRealescapestring($_POST['abc_meta_title']);
	$abc_address = mysqlRealescapestring($_POST['abc_address']);
	$abc_meta_desc = mysqlRealescapestring($_POST['abc_meta_desc']);
	$abc_id = mysqlRealescapestring($_POST['abc_id']);
	$abc_display_front = ($_POST['abc_display_front']);
	$abc_display_order = intval($_POST['abc_display_order']);
	$abc_top_company = mysqlRealescapestring($_POST['abc_top_company']);
	$digital_image_name = mysqlRealescapestring($_POST['digital_image_name']);
	$digital_image_delete = mysqlRealescapestring($_POST['digital_image_delete']);
	$old_image = mysqlRealescapestring($_POST['old_image']); 
	if($access_array['aba_access_company_branded']['abaac_manage']=='Yes'){ 
		$abc_branded = ($_POST['abc_branded']);
	}

	if($abc_active == 'Y'){
		$set_prod_status='Y';
		$prod_status='D';
	}else if($abc_active == 'N'){
		$set_prod_status='D';
		$prod_status='Y';
	}
	
	$msg = '';
	
	if($digital_image_name!=''){
		$image = handlefileupload($old_image,trim($digital_image_name),'company');
		
	}else{
		$image = $old_image;
	}
	if($digital_image_delete=='Y'){
		unlink('images/'.$image);
		$image = '';
	}

	$digital_image_name_banner = mysqlRealescapestring($_POST['digital_image_name_banner']);
	$digital_image_delete_banner = mysqlRealescapestring($_POST['digital_image_delete_banner']);
	$old_image_banner = mysqlRealescapestring($_POST['old_image_banner']); 
	if ($digital_image_name_banner != '') {
		$image_banner = fileuploadimg($old_image_banner, trim($digital_image_name_banner), 'company_banner');
	} else {
		$image_banner = $old_image_banner;
	}
	if ($digital_image_delete_banner == 'Y') {
		unlink('../images/' . $old_image_banner);
		$image_banner = '';
	}
	
		if($abc_id>0){
			$query1 = "UPDATE ab_company SET
					  abc_active = '".$abc_active."',
					  abc_name = '".$abc_name."',
					  abc_desc = '".$abc_desc."',
					  abc_display_front = '".$abc_display_front."',
					  abc_display_order = '".$abc_display_order."',
					  abc_top_company = '".$abc_top_company."',
					  abc_meta_title = '".$abc_meta_title."',
					  abc_meta_desc = '".$abc_meta_desc."',
					  abc_address = '".$abc_address."',
					  abc_image = '".$image."',
					  abc_banner_image = '".$image_banner."'";
					if($access_array['aba_access_company_branded']['abaac_manage']=='Yes'){ 
					  $query1 .= ",abc_branded = '".$abc_branded."'";
					}
					$query1 .= "WHERE abc_id = '".$abc_id."'";
					$query = $query1;
					  $msg = 'up';
					   
			//$query_product =mysqlQuery("UPDATE ab_product SET
			//		  abpd_active = '".$set_prod_status."'
			//		  WHERE abpd_comp_id = '".$abc_id."' AND abpd_active='".$prod_status."'");
					  	
					  Activitylog('Company / Manufacturer Updated',$abc_name.' Successfully Updated',$_SESSION['admin_id'],'admin');	  
		}else{
			$query1 = "INSERT INTO ab_company SET
					  abc_active = '".$abc_active."',
					  abc_name = '".$abc_name."',
					  abc_desc = '".$abc_desc."',
					  abc_display_front = '".$abc_display_front."',
					  abc_display_order = '".$abc_display_order."',
					  abc_top_company = '".$abc_top_company."',
					  abc_meta_title = '".$abc_meta_title."',
					  abc_meta_desc = '".$abc_meta_desc."',
					  abc_address = '".$abc_address."',
					  abc_image = '".$image."',
					  abc_banner_image = '".$image_banner."'";
					  if($access_array['aba_access_company_branded']['abaac_manage']=='Yes'){ 
					  	$query1 .=",abc_branded = '".$abc_branded."'";
						}
						$query = $query1;
					  $msg = 'in';	
					  
					  Activitylog('Company / Manufacturer Added',$abc_name.' Successfully Added',$_SESSION['admin_id'],'admin');
		}
		$res = mysqlQuery($query) or die(mysqlError());
		
		//header('location:Manage_companyes.php?msg='.$msg);
		echo $msg;
		exit();	
}

?>
<?php
	include("header.php");
?>
<script type="text/javascript" src="js/jquery.form.js"></script>
<script type="text/javascript" src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="//cdn.datatables.net/buttons/1.5.6/js/
dataTables.buttons.min.js"></script>
<script type="text/javascript" src="//cdn.datatables.net/buttons/1.5.6/js/
buttons.html5.min.js"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<style type="text/css">
	.dt-buttons{display: none;}
</style>
<script>
	jQuery(document).ready(function(){
		
		var msg = '<?php echo $_REQUEST['msg']; ?>';
		if(msg!=''){
			if(msg=='sub'){
				jAlert('Company Allready Exit!','Alert Dialog');
			}
			else if(msg=='in'){
				jAlert('Company Added Successfully!','Alert Dialog');
			}else if(msg=='up'){
				jAlert('Company Updated Successfully!','Alert Dialog');
			}
		}
		/*var fixNewLine = {
        exportOptions: {
        	columns: [0,1,2,3,4,5,6],
            format: {
                body: function ( data, column, row ) {
                    var processedData = (column === 0 || column === 1 || column === 2) ?
                        data.replace( /<br\s*\/?>/ig, "\n" ) :
                        data;
                    processedData = (column === 0 || column === 1 || column === 2) ?
                        processedData.replace( /<p\s*\/?>/ig, "\n" ) :
                        processedData;
                    processedData = (column === 0) ?
                        processedData.replace( '</span>', "\n" ) :
                        processedData;
                    processedData = processedData.replace(/<.*?>/g, "");
                    
                    return processedData;
                }
            		// body: function ( data,inner, rowidx, colidx, node ) {
            		// 	console.log(data+'--'+inner+'--'+rowidx+'--'+colidx+'--'+node)
					      //   if (jQuery(node).children("input").length > 0) {
					      //     return jQuery(node).children("input").first().val();
					      //   }else if (jQuery(node).children("img").length > 0) {
					      //   	return inner;
					      //   } else {
					      //     return data;
					      //   }
					    	// }
            }
        }
    };*/
		var datatbl = jQuery('#dyntable').dataTable({
			 "iDisplayLength": 100,   
			 "sPaginationType": "full_numbers",
			 "aaSorting": [],
			 /*"dom": '<"dt-top-container"<l><"dt-center-in-div"B><f>r>t<"dt-filter-spacer"f><ip>',
			 "buttons": [
          jQuery.extend( true, {}, fixNewLine, {
                extend: 'excelHtml5',
                title : '',
                footer: true,
                filename:'Company-report',
                stripHtml: true,
            } )],*/
		});
		 /*jQuery(".exportfilter").click(function(e){
      e.preventDefault();
      jQuery(".dt-buttons .buttons-excel").click();
    });*/
		
		jQuery('.code_data').live('click',function(){
			jQuery('.line3').removeClass('line3');
			jQuery(this).addClass('line3');		
			var obj = jQuery(this);
			
			jQuery('#abc_active').val(obj.data('abc_active'));
			jQuery('#abc_name').val(obj.data('abc_name'));
			/*jQuery('#abc_name_hindi').val(obj.data('abc_name_hindi'));*/
			jQuery('#abc_desc').val(obj.data('abc_desc'));
			jQuery('#abc_meta_title').val(obj.data('abc_meta_title'));
			jQuery('#abc_meta_desc').val(obj.data('abc_meta_desc'));
			jQuery('#abc_address').val(obj.data('abc_address'));
			
			if(obj.data('abc_display_front') == 'Y')
			{
				jQuery('#abc_display_front').prop('checked', true);
			}else{
				jQuery('#abc_display_front').prop('checked', false);
			}
			jQuery('#abc_display_front').val(obj.data('abc_display_front'));
			if(obj.data('abc_branded') == 'Y')
			{
				jQuery('#abc_branded').prop('checked', true);
			}else{
				jQuery('#abc_branded').prop('checked', false);
			}
			jQuery('#abc_branded').val(obj.data('abc_branded'));

			if(obj.data('abc_display_order') == '0')
			{
				jQuery('#abc_display_order').val('');
			}else{
				jQuery('#abc_display_order').val(obj.data('abc_display_order'));
			}
			jQuery('#abc_id').val(obj.data('abc_id'));
			jQuery('#abc_top_company').val(obj.data('abc_top_company'));
			var image = '';
			if(obj.data('abc_image')!=''){
				image = "images/"+obj.data('abc_image');
				jQuery('#imagebox').html('<img width="100px;" onerror="this.src=\'images/bg1.png\'" src="'+image+'">');
				jQuery('#old_image').val(obj.data('abc_image'));
				jQuery('.imageLink').attr('rel','upload_image_popup.php?table=ab_company&field=abc_image&id_field=abc_id&id='+obj.data('abc_id'));
			}else{
				jQuery('#imagebox').html('');
				jQuery('#old_image').val('');
				jQuery('.imageLink').attr('rel','upload_image_popup.php?table=ab_company&field=abc_image&id_field=abc_id');
			}
        
        	// Banner Image
			var imageBanner = '';
			var imageBannerUrl = '<?php echo $banner_url; ?>';
			if(obj.data('abc_banner_image')!=''){
				imageBanner = "../images/"+obj.data('abc_banner_image');
				jQuery('#imageboxBanner').html('<img width="100px;" onerror="this.src=\'images/bg1.png\'" src="'+imageBanner+'">');
				jQuery('#old_image_banner').val(obj.data('abc_banner_image'));
				imageBannerUrl = imageBannerUrl+'&id='+obj.data('abc_id');
			}else{
				jQuery('#imageboxBanner').html('');
				jQuery('#old_image_banner').val('');
			}
			jQuery('.imageLinkBanner').attr('rel',imageBannerUrl);
			
			fillproducts(jQuery('#abc_id').val());
		});
		jQuery('#add_data').on('click',function(){
			jQuery('.line3').removeClass('line3');
			jQuery('#abc_active').val('');
			jQuery('#abc_name').val('');
			/*jQuery('#abc_name_hindi').val('');*/
			jQuery('#abc_desc').val('');
			jQuery('#abc_meta_title').val('');
			jQuery('#abc_meta_desc').val('');
			jQuery('#abc_display_front').prop('checked', false);
			jQuery('#abc_branded').prop('checked', false);
			jQuery('#abc_display_order').val('');
			jQuery('#abc_id').val('');
			jQuery('#abc_address').val('');
			jQuery("#tblDiscount").html("");
        	jQuery('#imageboxBanner').html('');
			jQuery('#old_image_banner').val('');
			jQuery('#digital_image_name_banner').val('');
			jQuery('#digital_image_delete_banner').val('N');
			jQuery('.imageLinkBanner').attr('rel','<?php echo $banner_url; ?>');
		});
		
	});
	function DeleteCompany(id){
		jConfirm('Are you sure you want to delete this Company?','Confirm Dialog',function(r){
			if(r){
				jQuery.ajax({
					url:'Manage_companyes.php',
					data:{abc_id:id,type:'delete'},
					type:'POST',
					success:function(data){
						if(data==''){
							jQuery('#comp_'+id).fadeOut('slow');
							jAlert('Company Deleted Successfully!','Alert Dialog');
							return false;
						}
					}
				});
			}
		});
	}
	
	
	
	jQuery(document).on("change", "#abc_top_company", function(){
	checkshowstatus(jQuery(this).val());
});

function fillproducts(id){
	jQuery.ajax({
		url: "ajax_process.php",
		type: "POST",
		data: {'company_id':id,'action':'get_product_company_wise_coupon'},
		dataType: "json",
		async:false,
		success:function(data) {
			var strProductlist = "";
			jQuery.each(data, function(key, value) {
				//jQuery('select[name="srchProduct[]"]').append('<option value="'+ value.abpd_id +'">'+ value.abpd_name +'</option>');
				strProductlist = strProductlist + "<tr><td>" + value.abpd_name + "</td><td><input type='text' class='numer_only' style='width:65px;' id='txtDiscount_"+value.abpd_id+"' name='txtDiscount_"+value.abpd_id+"'><input type='hidden' id='txtproduct_"+value.abpd_id+"' name='txtproduct_"+value.abpd_id+"' value='" + value.abpd_id + "'></td></tr>";
			});
			
			jQuery("#tblDiscount").html(strProductlist);
		}
	});
	jQuery('#discount_table_new').dataTable().fnDestroy();
	jQuery('#discount_table_new').dataTable({
		"bPaginate": false,
		"bFilter": true,
		"bordering": false,
		"bSort" : false,
		"bInfo": false
	});
}

function checkshowstatus(staus){
	
	if(staus == 'yes'){
		jQuery('#sbmt-btn-disp-blk').prop('disabled', true);
		jQuery.ajax({
			url:'Manage_companyes.php',
			type:'POST',
			async:false,
			data:{type:'get_show_on_home_count'},
			success:function(data){
				if(data == 'no'){
					jAlert('You can not select more then <?php echo $MAXIMUM_TOP_COMPANIES_COUNT; ?> companies as top companies.','Alert Dialog');
					jQuery('#abc_top_company').val('no');
				}
				jQuery('#sbmt-btn-disp-blk').prop('disabled', false);
			}
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
<div class="rightpanel">
<ul class="breadcrumbs">
  <li><a href="dashboard.php"><i class="iconfa-home"></i></a> <span class="separator"></span></li>
  <li>Manage Products <span class="separator"></span></li>
  <li>Company / Manufacturer</li>
</ul>
<div class="pageheader">
  <?php if($access_array['aba_access_company']['abaac_manage']=='Yes'){ ?>
  <div class="searchbar">
    <button id="add_data" class="btn btn-primary btn-large">Add</button>
  </div>
  <?php } ?>
  <!--<form action="results.html" method="post" class="searchbar">
                <input type="text" name="keyword" placeholder="To search type and hit enter..." />
            </form>-->
  <div class="pageicon"><span class="iconfa-shopping-cart"></span></div>
  <div class="pagetitle">
    <h5>Add, Edit Or Delete All of Your Company / Manufacturer.</h5>
    <h1>Manage Company / Manufacturer</h1>
  </div>
</div>
<!--pageheader-->
<div class="maincontent">
  <div class="maincontentinner">
    <div class="row-fluid" >
      <?php if($access_array['aba_access_company']['abaac_manage']=='Yes'){ ?>
      <div class="span8">
        <?php }else{ ?>
        <div class="span12">
          <?php } ?>
          <div class="widgetbox">
            <div class="headtitle">
              <h4 class="widgettitle">Company / Manufacturer</h4>
            </div>
            <div class="widgetcontent">
            
            	<div style="overflow-x:auto;">
                	<form name="srchFrm" id="srchFrm" method="post" style="position:relative;">
                    <table cellpadding="0" cellspacing="0" class="table responsive" >
                      <tbody>
                        <tr>
                            <td>
                                <select id="ddStatus" name="ddStatus">
                                	<option value="">All</option>
                                    <option value="Y" <?php if($_REQUEST['ddStatus']=="Y"){ echo 'selected'; } ?>>Active</option>
                                    <option value="N" <?php if($_REQUEST['ddStatus']=="N"){ echo 'selected'; } ?>>Inactive</option>
                                </select>
                            </td>
                            <td>
                                <select id="ddBrand" name="ddBrand">
                                	<option value="">All</option>
                                    <option value="Y" <?php if($_REQUEST['ddBrand']=="Y"){ echo 'selected'; } ?>>Branded</option>
                                    <option value="N" <?php if($_REQUEST['ddBrand']=="N"){ echo 'selected'; } ?>>Non-Branded</option>
                                </select>
                            </td>
                            <td>
                                <input type="submit" name="srchSubmit" id="srchSubmit" class="btn btn-primary btn-large" value="Search">
                                <input type="submit" class="btn btn-primary btn-large" formaction="export_company.php" value="Export">
                            </td>
                        </tr>
                      </tbody>
                    </table>
                  </form>
                </div>
            
              <div style="overflow-x:auto; overflow-y:hidden;">
                <table id="dyntable" style="width:100%;" cellpadding="0" cellspacing="0" class="table table-bordered responsive" >
                  <colgroup>
                  <col class="con0" style="width:5%;">
                  <col class="con1" style="width:40%;">
                  <col class="con1" style="width:10%;">
                  <col class="con1" style="width:10%;">
                  <col class="con1" style="width:10%;">
                  <col class="con0" style="width:10%;">
                  <col class="con0" style="width:10%;">
                   <col class="con1" style="width:15%;">
                  </colgroup>
                  <thead>
                    <tr>
                      <th class="center">Status</th>
                      <th class="center">Name</th>
                      <th class="center">Display In Front</th>
                      <th class="center">Display Order</th>
                      <th class="center">Top Companies</th>
                      <th class="center">Branded</th>
                      <th class="center">Total Product</th>
                      <th class="center">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
					$strwhere = "";
					if($_POST['srchSubmit']!=""){
						$ddBrand = $_POST['ddBrand'];
						$ddStatus = $_POST['ddStatus'];
						
						if($ddBrand=="Y"){
							$strwhere = "WHERE abc_branded='".$ddBrand."'";
						}else if($ddBrand=="N"){
							$strwhere = "WHERE (abc_branded='".$ddBrand."' OR abc_branded='')";
						}
						if($strwhere!="" && $ddStatus!=""){
							$strwhere.=" AND abc_active='".$ddStatus."'";
						}else if($ddStatus!=""){
							$strwhere.=" WHERE abc_active='".$ddStatus."'";
						}
						
					}
					
					$query = "SELECT abc_id,abc_name,abc_name_hindi,abc_active,abc_desc,abc_display_front,abc_display_order,abc_top_company,abc_image,abc_banner_image,abc_meta_title,abc_meta_desc,abc_address,abc_branded 
							  FROM ab_company ".$strwhere." ORDER BY abc_id DESC";
					$res = mysqlQuery($query);													
					while($row = mysqlFetchArray($res)){
					$used = false;
					if(mysqlNumRow(mysqlQuery("SELECT abpd_id FROM ab_product WHERE abpd_comp_id = '".$row['abc_id']."'"))>0){
						$used = true;
					}
													
													
												?>
                    <tr id="comp_<?php echo $row['abc_id']; ?>" 
												data-abc_name="<?php echo $row['abc_name']; ?>" 
                        data-abc_desc="<?php echo $row['abc_desc']; ?>" 
												data-abc_active="<?php echo $row['abc_active']; ?>" 
												data-abc_id="<?php echo $row['abc_id']; ?>" 
                        data-abc_display_front="<?php echo $row['abc_display_front']; ?>" 
                        data-abc_display_order="<?php echo $row['abc_display_order']; ?>" 
                        data-abc_top_company="<?php echo $row['abc_top_company']; ?>"
                        data-abc_meta_title="<?php echo $row['abc_meta_title']; ?>"
                        data-abc_meta_desc="<?php echo $row['abc_meta_desc']; ?>"
                        data-abc_address="<?php echo $row['abc_address']; ?>"
                        data-abc_image="<?php echo $row['abc_image']; ?>"
						data-abc_banner_image="<?php echo $row['abc_banner_image']; ?>"
                        data-abc_branded="<?php echo $row['abc_branded']; ?>"
                        class="code_data">
                      <td class="center codedata"><?php if($row['abc_active']=='N'){ ?>
                        <img src='images/inactive-16.png' title="Inctive">
                        <?php }else{ ?>
                        <img src='images/active-16.png' title="Active">
                        <?php } ?>
                      </td>
                      <td><a href="<?php echo $SITE_STATIC_URL.'brands/'.strtolower(str_replace(' ', '_', str_replace('.', '|', $row['abc_name']) ) ).".php"; ?>" target="_blank"><?php echo $row['abc_name']; ?></a></td>
                      <!-- <td><?php //echo $row['abc_name_hindi']; ?></td> -->
                      <td><input type="checkbox" name="display_front" id="display_front" value="Y" <?php echo $row['abc_display_front'] == 'Y' ? 'checked="checked"' : ''; ?> onClick="updateFront(this,'<?php echo $row['abc_id']; ?>')"/></td>
                      <td><input class="numer_only" name="display_order_<?php echo $row['abc_id']; ?>" id="display_order" style="width:50px;" type="text" value="<?php echo $row['abc_display_order']; ?>" onChange="updateOrder(this,'<?php echo $row['abc_id']; ?>')"/></td>
                      <td><?php echo ucfirst($row['abc_top_company']); ?></td>
                      <td><?php echo $row['abc_branded']=="Y"?"Yes":"No"; ?></td>
                      <td>
                      	<?php $sel_tot_prod = "SELECT p.abpd_id FROM ab_product as p WHERE p.abpd_comp_id = '".$row['abc_id']."' AND abpd_active ='Y' ";
                      	$sel_tot_prod_qry = mysqlQuery($sel_tot_prod);
                      	$sel_tot_prod_num_row = mysqlNumRow($sel_tot_prod_qry);
                      	?>
                      	<a target="_blank" href="<?php echo 'manage_products.php?comp_id='.$row['abc_id'].'&active_product=Y';?>"><?php echo $sel_tot_prod_num_row; ?></a>
                      	 
                      </td>
                      <td class="center"><?php if($access_array['aba_access_company']['abaac_manage']=='Yes'){ ?>
                        <img title="Edit" style="cursor:pointer;" alt="Edit" src="images/Edit - 16.png" id="edit_banner">&nbsp;
                        <?php } ?>
                        <?php if($access_array['aba_access_company']['abaac_delete']=='Yes'){ ?>
                        <img id="delete_banner" style="cursor:pointer;" <?php if($used){?> onClick="javascript:jAlert('This Company have a Product. Can not Delete!','Alert Dialog'); return false;" <?php }else{ ?> onClick="DeleteCompany('<?php echo $row['abc_id']; ?>')" <?php } ?> title="Delete" alt="Delete" src="images/Delete - 16.png" >
                        <?php } ?>
                        <?php if($access_array['aba_access_company']['abaac_manage']=='Yes'){ ?>
                        	<a href="add_company_lang.php?company_id=<?php echo $row['abc_id']; ?>"><img title="Company Language" style="cursor:pointer;width: 20px;height: 20px;" alt="Company Language" src="images/language_icon.jpg"></a>
                        <?php } ?>
                      </td>
                    </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <?php if($access_array['aba_access_company']['abaac_manage']=='Yes'){ ?>
        <div class="span4">
          <div class="widgetbox">
            <div class="headtitle">
              <h4 class="widgettitle">Add/Edit Company / Manufacturer</h4>
            </div>
            <div class="widgetcontent" >
              <form id="company_frm" action="" name="company_form" method="POST">
                <input type="hidden" id="abc_id" name="abc_id" value="" >
                <label class="control-label">Status:<span style="color:red;">*</span></label>
                <span style="margin:0;" class="field">
                <select id="abc_active" name="abc_active">
                  <option value="">- - - Select Status - - - </option>
                  <option value="Y">Active</option>
                  <option value="N">Inactive</option>
                </select>
                </span>
                <label class="control-label">Name:<span style="color:red;">*</span></label>
                <span style="margin:0;" class="field">
                <input type="text" value="" id="abc_name" name="abc_name">
                </span>
                <!-- <label class="control-label">Name In Hindi:</label>
                                            <span style="margin:0;" class="field">
                                            	<input type="text" value="" id="abc_name_hindi" name="abc_name_hindi">
                                            </span> -->
                <label class="control-label">Description :</label>
                <span style="margin:0;" class="field">
                <textarea id="abc_desc" name="abc_desc"></textarea>
                </span>
                <label class="control-label">Top Companies :</label>
                <span style="margin:0;" class="field">
                <select name="abc_top_company" id="abc_top_company" class="input-file-cts" tabindex="7">
                  <option value="no">No</option>
                  <option value="yes">Yes</option>
                </select>
                </span>
                
                 <label class="control-label">Meta Title:<span style="color:red;">*</span></label>
                  <span style="margin:0;" class="field">
                     <input type="text" value="" id="abc_meta_title" name="abc_meta_title">
                  </span>

                  <label class="control-label">Meta Description :<span style="color:red;">*</span></label>
                  <span style="margin:0;" class="field">
                <textarea id="abc_meta_desc" name="abc_meta_desc"></textarea>
                </span>
                
                <label class="control-label">Image:</label>
                <span style="margin:0;" class="field"> <span id="imagebox" style="margin:0; width:100%; float:left;"></span>
                <input type="hidden" name="old_image" id="old_image" value=""/>
                <input type="hidden" name="digital_image_name" id="digital_image_name" value="">
                <input type="hidden" name="digital_image_delete" id="digital_image_delete" value="N">
                <a rel="upload_image_popup.php?table=ab_company&field=abc_image&id_field=abc_id" role="button" class="btn btn-primary imageLink"  >Upload</a> </span>
                
                <label class="control-label">Banner Image:</label>
				<span style="margin:0;" class="field">                                            	
					<span id="imageboxBanner" style="margin:0; width:100%; float:left;"></span>                            
					<input type="hidden" name="old_image_banner" id="old_image_banner" value=""/>
					<input type="hidden" name="digital_image_name_banner" id="digital_image_name_banner" value="">
					<input type="hidden" name="digital_image_delete_banner" id="digital_image_delete_banner" value="N">
					<a rel="<?php echo $banner_url; ?>" role="button" class="btn btn-primary imageLinkBanner">Banner Upload</a>
				</span>
                
                <label class="control-label">Display On Front:</label>
                <span style="margin:0;padding-bottom: 5px;float: left;width: 100%;" class="field">
                <input type="checkbox" value="Y" id="abc_display_front" name="abc_display_front" <?php $abc_display_front == 'Y' ? 'checked="checked"' : ''; ?>>
                </span>
                <label class="control-label">Display Order:</label>
                <span style="margin:0;" class="field">
                <input class="numer_only" type="text" value="" id="abc_display_order" name="abc_display_order">
                </span>
                
                <label class="control-label">Address:</label>
                <span style="margin:0;" class="field">
                <textarea id="abc_address" name="abc_address" rows="4"></textarea>
                </span>
                
                <?php if($access_array['aba_access_company_branded']['abaac_manage']=='Yes'){ ?>
	                <label class="control-label">Branded:</label>
	                <span style="margin:0;padding-bottom: 5px;float: left;width: 100%;" class="field">
	                <input type="checkbox" value="Y" id="abc_branded" name="abc_branded" <?php $abc_branded == 'Y' ? 'checked="checked"' : ''; ?>>
	                </span>
	              <?php } ?>
                <label class="control-label"></label>
                <span  style="margin:0;" class="field">
                <input type="reset" class="btn" value="Reset">
                <input type="button" name="submit" onClick="return validate()" class="btn btn-primary" value="Submit" id="sbmt-btn-disp-blk">
                </span>
              </form>
            </div>
          </div>
        </div>
        
        <!-- Online payment discount setup -->
        <div class="span4">
          <div class="widgetbox">
            <div class="headtitle">
              <h4 class="widgettitle">Update Online Discount for Products</h4>
            </div>
            <div class="widgetcontent" >
              <form name="discount_prod" id="discount_prod" method="post" action="">
                	<div class="recomadend_wrap" style="max-height:300px;overflow-y:auto;">
                        <table style="width:100%;" cellpadding="0" cellspacing="0" class="table table-bordered responsive" id="discount_table_new" >
                                <colgroup>                                        	
                                    <col class="con0" style="width:70%;">                                            
                                    <col class="con1" style="width:20%;">
                                 </colgroup>
                            <thead>
                                <th>Product Name</th>
                                <th>Discount (%)</th>
                            </thead>
                            <tbody id="tblDiscount">
                            </tbody>
                        </table>
                     </div>
                  </form>
            </div>
          </div>
        </div>
        <!-- End: Online payment discount setup -->
        <?php } ?>
        <?php include('Include/footer.php'); ?>
      </div>
    </div>
  </div>
</div>
<div id="imageModal" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="width: auto;">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    <h3 id="myModalLabel">Add/Edit Image</h3>
  </div>
  <div class="modal-body " id="mymodalhtml"> </div>
  <div class="modal-footer" style="text-align:center;">
    <button id="cancel_btn" aria-hidden="true" data-dismiss="modal" class="btn ">Cancel</button>
    <button class="btn btn-primary" onClick="save_imgdigital();" ><span class="icon-ok icon-white"></span> Save Changes</button>
  </div>
</div>
<script>
	jQuery(document).ready(function(){
		jQuery('#company_frm').on('keydown',function(event){
			if(event.which==13 && event.target.tagName !== 'TEXTAREA'){
				validate();
				return false;
			}
		});	
			jQuery('.numer_only').on('keydown',function(e){
	
			var val = jQuery(this).val();
	
				
	
				// Allow: backspace, delete, tab, escape, enter and .
	
				//alert(e.keyCode+'=>'+val);
	
				if (e.keyCode == 110 && val.indexOf('.') !== -1){
	
					e.preventDefault();
	
					return false;
	
				}
	
				
	
			if (jQuery.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
	
				 // Allow: Ctrl+A
	
				(e.keyCode == 65 && e.ctrlKey === true) || 
	
				 // Allow: home, end, left, right
	
				(e.keyCode >= 35 && e.keyCode <= 39)) {
	
					 // let it happen, don't do anything
	
					 return;
	
			}
	
			// Ensure that it is a number and stop the keypress
	
			if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
	
				e.preventDefault();
	
			}
		});
	});
	function formReset()
	{
		jQuery("#add_data").click();
	}
	function validate(){
		if(jQuery('#abc_active').val()==''){
			jAlert('Please Select Status!','Alert Dialog');
			return false;
		}else if(jQuery('#abc_name').val()==''){
			jAlert('Please Enter Name!','Alert Dialog');
			return false;
		}else if(jQuery('#abc_meta_title').val()==''){
            jAlert('Please Enter Meta Title!','Alert Dialog');
            return false;
        }else if(jQuery('#abc_meta_desc').val()==''){
            jAlert('Please Enter Meta Desc!','Alert Dialog');
            return false;
		}/*else if(jQuery('#abc_name_hindi').val()==''){
			jAlert('Please Enter Name In Hindi!','Alert Dialog');
			return false;
		}*/else{
			var display_front = jQuery('#abc_display_front').is(':checked');
			
			if(display_front == true)
			{
				display_front = 'Y';
			}else{
				display_front = '';
			}
			var abc_branded = jQuery('#abc_branded').is(':checked');
			
			if(abc_branded == true)
			{
				abc_branded = 'Y';
			}else{
				abc_branded = '';
			}
			
			
			jQuery.ajax({
				url:'Manage_companyes.php',
				type:'POST',
				data:{'type':"check",'abc_name':jQuery("#abc_name").val(),"abc_id":jQuery('#abc_id').val()},
				success:function(data){					
					
					if(data=='Yes'){
						jAlert('Company Name Already Exist!','Alert Dialog');						
					}else{
						jQuery.ajax({
							url:'Manage_companyes.php',
							type:'POST',
							data:{'submit':"Submit","abc_active":jQuery('#abc_active').val(),"abc_name":jQuery('#abc_name').val(),"abc_name_hindi":jQuery('#abc_name_hindi').val(),"abc_desc":jQuery('#abc_desc').val(),"abc_meta_title":jQuery('#abc_meta_title').val(),"abc_meta_desc":jQuery('#abc_meta_desc').val(),"abc_address":jQuery('#abc_address').val(),"abc_id":jQuery('#abc_id').val(),"abc_display_front":display_front,"abc_display_order":jQuery('#abc_display_order').val(),'abc_top_company':jQuery('#abc_top_company').val(),"digital_image_name":jQuery("#digital_image_name").val(),"digital_image_delete":jQuery('#digital_image_delete').val(),"old_image":jQuery('#old_image').val(),"digital_image_name_banner":jQuery("#digital_image_name_banner").val(),"digital_image_delete_banner":jQuery('#digital_image_delete_banner').val(),"old_image_banner":jQuery('#old_image_banner').val(),"abc_branded":abc_branded},
							success:function(data){
								if(data){
									window.location.href='Manage_companyes.php?msg='+data;
								}
							}
							
						});
						
						
					}
				}
			
			});
			
			
		}
		
		
	}
	jQuery('.imageLink').live('click',function(){
		jQuery('#mymodalhtml').html('');
		var url = jQuery(this).attr('rel');
		var field = jQuery(this).attr('field')		
		jQuery.ajax({
			url:url,
			type:'POST',
			data:{field:field},
			success:function(data){
				jQuery('#mymodalhtml').html(data);
				jQuery('#imageModal').modal('show');
			}
		});
	});
	
	jQuery('.imageLinkBanner').live('click',function(){
		jQuery('#mymodalhtml').html('');
		var url = jQuery(this).attr('rel');
		var field = jQuery(this).attr('field')		
		jQuery.ajax({
			url:url,
			type:'POST',
			data:{field:field},
			success:function(data){
				jQuery('#mymodalhtml').html(data);
				jQuery('#imageModal').modal('show');
			}
		});
	});

	function updateOrder(id,company_id){
		
		var display_order = jQuery(id).val();
		
		jQuery.ajax({
				url:'Manage_companyes.php',
				type:'POST',
				data:{'type':"UPDATE_ORDER",company_id:company_id,display_order:display_order},
				success:function(data){	
					if(data == 'up')
					{
						jAlert('Company Display Order Updated Succesfully!','Alert Dialog');
					}
				}
		});
	}
	function updateFront(id,company_id){
	
		var display_front = jQuery(id).is(':checked');;
		
		if(display_front == true)
		{
			display_front = 'Y';
		}else{
			display_front = 'N';
		}
		
		jQuery.ajax({
				url:'Manage_companyes.php',
				type:'POST',
				data:{'type':"UPDATE_FRONT",company_id:company_id,display_front:display_front},
				success:function(data){	
					if(data == 'up')
					{
						jAlert('Company Display Front Updated Succesfully!','Alert Dialog');
					}
				}
		});
		
	}
</script>
</body>
</html>