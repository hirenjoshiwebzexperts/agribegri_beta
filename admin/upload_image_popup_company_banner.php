<?php
include('internalaccess/session.php');
include('internalaccess/connectdb.php');

$table = '';
$field = 'image';
$id = $_REQUEST['id'];
if ($_REQUEST['table'] != '') {
    $table = $_REQUEST['table'];
}
if ($_REQUEST['field'] != '') {
    $field = $_REQUEST['field'];
}
$id_field = 'id';
if ($_REQUEST['id_field'] != '') {
    $id_field = $_REQUEST['id_field'];
}


$image_banner = '';
$image_banner_url = '';
if ($id > 0 && $id != '') {
    $sql = "SELECT $field as image from $table where $id_field = '$id'";
    $res = mysqlQuery($sql);
    $row = mysqlFetchArray($res);
    $image_banner = $row['image'];
}
?>
<script>
    var global_imgdigital_upload = false;
    var counter = false;
    function imgdigital_btn() {
        jQuery('#image_imgdigital_banner').click().change(function(evt) {
        	var files = evt.target.files; // FileList object
        	var formdata;
        	if (window.FormData) {
            	formdata = new FormData();
        	}
        	f = files[0];
        	var reader = new FileReader();
        	//Read the contents of Image File.
        	reader.readAsDataURL(f);
        	reader.onload = function (e) {

            	//Initiate the JavaScript Image object.
            	var image = new Image();

            	//Set the Base64 string return from FileReader as source.
            	image.src = e.target.result;

            	//Validate the File Height and Width.
            	image.onload = function () {
                	var height = this.height;
                	var width = this.width;
                	if (width != 1268 || height!=250)
                	{
                    	alert("Please upload image 1268w X 250h");
                    	return false;
                	}
                	else {
                    	handleFileSelect(evt,'imgdigital');
                    	jQuery('body').focus();
                	}
            	}
        	};
            // handleFileSelect(evt, 'imgdigital');
            // jQuery('body').focus();
        });
    }

    function imgdigital_delete() {
        jQuery('#digital_image_name_banner').val('');
        jQuery('#digital_image_delete_banner').val('Y');
        jQuery('#imageboxBanner').html('');

        jQuery('#cancel_btn').trigger('click');
    }

    function handleFileSelect(evt, id) {
        var files = evt.target.files; // FileList object
        var formdata;
        if (window.FormData) {
            formdata = new FormData();
        }
        f = files[0];

        /*====== File size validation while upload starts =======*/
        var file_size = f.size;

        if (file_size > 300000 && counter == false) {
            counter = true;
            alert("Please upload maximum 300kb image!");
            return false;
        }
        /*====== File size validation while upload ends =======*/

        // Only process image files.
        if (!f.type.match('image.webp')) {
        	alert("Please upload WEBP image!");
            return false;
        }
    	
    	/*// Create array of good name extensions
        const acceptedTypes = ['webp'];
        // Create array of good types
        const acceptedTypesLong = ['image/webp'];

        const nameExtension = f.name.split('.').pop();
            
        // 1) Check that the name is compliant
        if (!acceptedTypes.includes(nameExtension)) {
			// This will remove the file from the input
        	alert("Please upload webp image!");
            return false;
		}
        // 2) Check that the File object type is compliant 
        if(!acceptedTypesLong.includes(f.type)) {
            // This will remove the file from the input
        	alert("Please upload webp image!");
            return false;
		}*/
    
        var reader = new FileReader();
        // Closure to capture the file information.
        reader.onload = (function(theFile) {
            return function(e) {
                document.getElementById(id).innerHTML = '<img class="imgpreview img-polaroid" src="' + e.target.result + '" title="' + theFile.name + '"/>';
                global_imgdigital_upload = true;
            };
        })(f);
        reader.readAsDataURL(f);
        if (!formdata) {
            formdata.append("images", f);
        }
    }

    function save_imgdigital() {
        if (global_imgdigital_upload) {
            jQuery('#imgdigital_form').submit();
        }
    }
    jQuery('#imgdigital_form').ajaxForm({
        beforeSend: function() {
            imgdigital_uploading = true;
            var percentVal = '0%';
            jQuery('#imgdigital_progress').show();
            jQuery('#imgdigital_bar').width(percentVal);
            jQuery('#imgdigital_percent').html(percentVal);
        },
        uploadProgress: function(event, position, total, percentComplete) {
            var percentVal = percentComplete + '%';
            jQuery('#imgdigital_bar').width(percentVal);
            jQuery('#imgdigital_percent').html(percentVal);
        },
        success: function() {
            var percentVal = '100%';
            jQuery('#imgdigital_bar').width(percentVal);
            jQuery('#imgdigital_percent').html(percentVal);
        },
        complete: function(xhr) {

            if (xhr.responseText.search("-.") != -1) {
                global_imgdigital = jQuery.trim(xhr.responseText);
                jQuery('#digital_image_name_banner').val(global_imgdigital);
                jQuery('#digital_image_delete_banner').val('N');
                jQuery("#imageboxBanner").show();
                jQuery('#imageboxBanner').html('<img src="temp_img/' + global_imgdigital + '" width="100px;">');
                console.log(global_imgdigital);
            } else {
                alert("Error occured on uploading this file");
            }
            imgdigital_uploading = false;
            global_imgdigital_upload = false;
            jQuery('#imgdigital_progress').hide();
            jQuery('#cancel_btn').trigger('click');
        }
    });
</script>

<div class="mediaWrapper row-fluid">
    <div class="span5 imginfo">
        <form id="imgdigital_form" name="imgdigital_form" method="post" action="upload_process.php" enctype="multipart/form-data">
            <input type="file" name="images" id="image_imgdigital_banner" style="display:none;" accept="image/webp" >
        </form>
        <div id="imgdigital">
            <?php if ($image_banner == '') { ?>
                <img src="images/photos/1.png" alt="" class="imgpreview img-polaroid" />
            <?php } else {
                $path_info = pathinfo($image_banner);
                $imgext =  $path_info['extension'];

                $image_banner_url = "../images/" . $image_banner; ?>
                <img src="<?= $image_banner_url ?>" alt="" class="imgpreview img-polaroid" />
            <?php } ?>
        </div>
        <div class="progress" id="imgdigital_progress" style="display:none;">
            <div class="bar" id="imgdigital_bar"></div>
            <div class="percent" id="imgdigital_percent">0%</div>
        </div>

        <p style="margin-top: 10px;">
            <a style="color: #0866C6;" href="javascript:imgdigital_btn();" class="btn btn-small"><span class="icon-pencil"></span> <?php if($id!=0 && $id!='') {?>Edit<?php }else{ ?>Upload<?php  } ?> Image</a> <br /><span style="color:red;font-size:11px;">Note : Max-width : 1268px & Min-Height : 250px; Max size: 300kb<br>The image should have a WEBP extensions.</span>
            <?php if($id!=0 && $id!='') {?><a style="color: #0866C6;" href="javascript:imgdigital_delete();" class="btn btn-small"><span class="icon-trash"></span> Delete Image</a><?php }?>
        </p>
        <p><span id="image_desc"></span></p>
    </div>
    <div class="span7 imgdetails">
        <p>
            <label>Name:</label>
            <input type="text" class="input-block-level" name="image_name" id="image_name" value="<?php echo basename($image_banner) ?>" readonly="true" />
        </p>
        <p>
            <label>Link URL:</label>
            <input type="text" class="input-block-level" name="image_url" id="image_url" value="<?php echo $image_banner_url ?>" readonly="true" />
        </p>
    </div>
</div>