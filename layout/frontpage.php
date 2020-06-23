<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package   theme_lambda
 * @copyright 2019 redPIthemes
 *
 */
 
$hasfrontpageblocks = ($PAGE->blocks->region_has_content('side-pre', $OUTPUT) || $PAGE->blocks->region_has_content('side-post', $OUTPUT));
$carousel_pos = $PAGE->theme->settings->carousel_position;
$carousel_img_dim = $PAGE->theme->settings->carousel_img_dim;
$carousel_img_dim = substr($carousel_img_dim, 0, -2);
$pagewidth = $PAGE->theme->settings->pagewidth;
$standardlayout = FALSE;
if ($PAGE->theme->settings->block_layout == 1) {$standardlayout = TRUE;}
$sidebar = FALSE;
if ($PAGE->theme->settings->block_layout == 2) {$sidebar = TRUE;}
if (($sidebar) && (strpos($OUTPUT->body_attributes(), 'empty-region-side-pre') !== FALSE) && (strpos($OUTPUT->body_attributes(), 'editing') == FALSE)) {$sidebar = FALSE;}
if ($sidebar) {theme_lambda_init_sidebar($PAGE); $sidebar_stat = theme_lambda_get_sidebar_stat();}

if (right_to_left()) {
    $regionbsid = 'region-bs-main-and-post';
} else {
    $regionbsid = 'region-bs-main-and-pre';
}

echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html() ?>
    

    

    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <noscript>
			<link rel="stylesheet" type="text/css" href="<?php echo $CFG->wwwroot;?>/theme/lambda/style/nojs.css" />
	</noscript>
    <!-- Google web fonts -->
    <?php require_once(dirname(__FILE__).'/includes/fonts.php'); ?>
</head>

<?php 
	$lambda_body_attributes = 'has-region-side-pre has-region-side-post';
	$hassidepre = $PAGE->blocks->region_has_content('side-pre', $OUTPUT);
	if ($hassidepre) {$lambda_body_attributes .= ' used-region-side-pre';} else {$lambda_body_attributes .= ' empty-region-side-pre';}
	$hassidepost = $PAGE->blocks->region_has_content('side-post', $OUTPUT);
	if ($hassidepost) {$lambda_body_attributes .= ' used-region-side-post';} else {$lambda_body_attributes .= ' empty-region-side-post';}
	if ($sidebar) {$lambda_body_attributes .= ' sidebar-enabled '.$sidebar_stat;}
	$blockstyle = theme_lambda_get_setting('block_style');
	if ($blockstyle == 0) {$lambda_body_attributes .= ' blockstyle-01';}
	if ($blockstyle == 1) {$lambda_body_attributes .= ' blockstyle-02';}
	if ($blockstyle == 2) {$lambda_body_attributes .= ' blockstyle-03';}
?>
<body <?php echo $OUTPUT->body_attributes("$lambda_body_attributes"); ?>>

<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<?php if ($sidebar) { ?>
<div id="sidebar" class="">
	<?php echo $OUTPUT->blocks('side-pre');?>
    <div id="sidebar-btn"><span></span><span></span><span></span></div>
</div>
<?php } ?>

<div id="wrapper">

<?php require_once(dirname(__FILE__).'/includes/header.php'); ?>
<?php require_once(dirname(__FILE__).'/includes/slideshow.php'); ?>

<div id="page" class="container-fluid">
	<div id ="page-header-nav" class="clearfix"> </div>
    <div id="page-content" class="row-fluid">
    	<?php if ($hasfrontpageblocks==1) { ?>
            <div id="<?php echo $regionbsid ?>" class="span9">
            <div class="row-fluid">
            	<?php if ($standardlayout) { ?>
                <section id="region-main" class="span8 pull-right">
                <?php } else { ?>
                <section id="region-main" class="span8">
                <?php } ?>
        <?php } else { ?>
        	<div id="<?php echo $regionbsid ?>">
            <div class="row-fluid">
            	<section id="region-main" class="span12">
        <?php } ?>
            	<?php
            		echo $OUTPUT->course_content_header();
					if ($carousel_pos=='0') require_once(dirname(__FILE__).'/includes/carousel.php');
            		echo $OUTPUT->main_content();
            		echo $OUTPUT->course_content_footer();
					if ($carousel_pos=='1') require_once(dirname(__FILE__).'/includes/carousel.php');
            	?>
        		</section>
        	<?php
        	if ($hasfrontpageblocks==1) { 
				if ($standardlayout) {echo $OUTPUT->blocks('side-pre', 'span4 desktop-first-column pull-left');}
				else if (!$sidebar) {echo $OUTPUT->blocks('side-pre', 'span4 desktop-first-column pull-right');}
			} ?>
            </div>
        	</div>
        <?php echo $OUTPUT->blocks('side-post', 'span3'); ?>
    </div>

    <?php if (is_siteadmin()) { ?>
	<div class="hidden-blocks">
    	<div class="row-fluid">
        	<h4><?php echo get_string('visibleadminonly', 'theme_lambda') ?></h4>
            <?php
                echo $OUTPUT->blocks('hidden-dock');
            ?>
    	</div>
	</div>
	<?php } ?>

	<a href="#top" class="back-to-top"><i class="fa fa-chevron-circle-up fa-3x"></i><span class="lambda-sr-only"><?php echo get_string('back'); ?></span></a>

	</div>
	<?php if ($CFG->version >= 2018120300) {echo $OUTPUT->standard_after_main_region_html();} ?>
	<footer id="page-footer" class="container-fluid">
		<?php require_once(dirname(__FILE__).'/includes/footer.php'); echo $OUTPUT->login_info();?>
	</footer>

	</div>

    <?php echo $OUTPUT->standard_end_of_body_html() ?>

<script>
<?php if ($hasslideshow) { ?>
	(function($) {
 		$(document).ready(function(){
		$('#camera_wrap').camera({
			fx: '<?php echo $imgfx; ?>',
			height: '<?php echo $slideshow_height; ?>',
			loader: '<?php echo $loader; ?>',
			thumbnails: false,
			pagination: false,
			autoAdvance: <?php echo $advance; ?>,
			hover: false,
			navigationHover: <?php echo $navhover; ?>,
			mobileNavHover: <?php echo $navhover; ?>,
			opacityOnGrid: false
		});
	 });
	}) (jQuery);
<?php } ?>

<?php if ($hascarousel) { ?>
	var width = $(window).innerWidth();
	(function($) {
 		$(document).ready(function(){
		$('.slider1').bxSlider({
			pager: false,
			nextSelector: '#slider-next',
			prevSelector: '#slider-prev',
			nextText: '>',
			prevText: '<',
			slideWidth: <?php echo $carousel_img_dim; ?>,
    		minSlides: 1,
    		maxSlides: (width < 430) ? 1 : 6,
			moveSlides: 0,
			shrinkItems: true,
			useCSS: true,
			wrapperClass: 'bx-wrapper',
    		slideMargin: 10	
		});
	 });
	}) (jQuery);
<?php } ?>
</script>

</body>
</html>