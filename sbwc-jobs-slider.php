<?php

/**
 * Plugin Name: Recruit CRM Jobs Slider
 * Plugin URI:
 * Description: A plugin to display job listings from Recruit CRM API in a carousel format using Owl Carousel.
 * Version: 1.0.0
 * Author: Werner Bessinger @ Silverback Dev Studios
 * Author URI: https://silverbackdev.co.za
 * License: GPL2
 */

// Register the SBWC Jobs Slider plugin
function sbwc_jobs_slider_plugin_init()
{
    // Register the admin page
    add_action('admin_menu', 'sbwc_jobs_slider_add_admin_page');

    // Register the shortcode
    add_shortcode('recruitcrm-jobs', 'sbwc_jobs_slider_shortcode');
}

add_action('init', 'sbwc_jobs_slider_plugin_init');

// Add the admin page
function sbwc_jobs_slider_add_admin_page()
{
    add_menu_page(
        'Recruit CRM Jobs Slider',
        'Recruit CRM Jobs Slider',
        'manage_options',
        'sbwc-jobs-slider',
        'sbwc_jobs_slider_admin_page_callback',
        'dashicons-admin-generic',
        20
    );
}

// Admin page callback function
function sbwc_jobs_slider_admin_page_callback()
{

    // update
    if (isset($_POST['crm-api-key']) && isset($_POST['crm-api-nonce'])) {

        // Verify the nonce
        if (!wp_verify_nonce($_POST['crm-api-nonce'], 'update crm api')) {
            wp_die('Invalid nonce');
        }

        // Update the API key in the database
        update_option('crm-api-key', sanitize_text_field($_POST['crm-api-key']));

        // Update jobs to load
        update_option('crm-load-jobs', sanitize_text_field($_POST['crm-load-jobs']));


        // Display a success message
        echo '<div class="notice notice-success is-dismissible"><p>API key updated successfully.</p></div>';
    }

?>

    <div class="wrap">

        <h1 style="background: white; padding: 10px 20px; box-shadow: 0px 2px 4px lightgrey;">Recruit CRM API Key</h1>

        <form action="" method="post">

            <!-- api key -->
            <p><label for="crm-api-key"><b><i>Enter your Recruit CRM API key below (required for jobs slider to work):</i></b></label></p>
            <p><input type="text" name="crm-api-key" id="crm-api-key" class="regular-text" placeholder="your API key" value="<?php echo get_option('crm-api-key'); ?>" required></p>

            <!-- select whether to load all jobs or only open jobs -->
            <p><label for="crm-load-jobs"><b><i>Load all jobs or only open jobs?</i></b></label></p>
            <p>
                <select name="crm-load-jobs" id="crm-load-jobs" required>
                    <option value="">Please select...</option>
                    <option value="all" <?php echo selected(get_option('crm-load-jobs'), 'all'); ?>>All jobs</option>
                    <option value="open" <?php echo selected(get_option('crm-load-jobs'), 'open'); ?>>Open jobs</option>
                </select>
            </p>

            <!-- submit -->
            <p><input type="submit" class="button button-primary" value="Save API Key"></p>

            <!-- nonce -->
            <input type="hidden" name="crm-api-nonce" value="<?php echo wp_create_nonce('update crm api'); ?>">

        </form>
    </div>

<?php }


// Shortcode callback function
function sbwc_jobs_slider_shortcode($atts)
{

    // load owl carousel css and js
    wp_enqueue_style('owl-carousel', plugin_dir_url(__FILE__) . 'dist/assets/owl.carousel.min.css');
    wp_enqueue_style('owl-theme', plugin_dir_url(__FILE__) . 'dist/assets/owl.theme.default.min.css');
    wp_enqueue_script('owl-carousel', plugin_dir_url(__FILE__) . 'dist/owl.carousel.min.js', array('jquery'), '2.3.4', true);

    // retrieve the API key from the database
    $api_key = get_option('crm-api-key');

    // retrieve job types to view
    $job_types = get_option('crm-load-jobs');

    // init and send request
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.recruitcrm.io/v1/jobs',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            "limit: 100",
            "Authorization: Bearer $api_key"
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    // decode response
    $decoded = json_decode($response, true);

    $data = $decoded['data'];

    // if empty array, log error to WP error log and bail
    if (empty($data)) {
        error_log('Recruit CRM API error: Empty response array.');
        return;
    }

    // generate placeholder text for jobs without description, at least 300 chars in length
    $placeholder_text = 'Parmesan bavarian bergkase cheese triangles. Camembert de normandie port-salut airedale cheesecake cut the cheese ricotta say cheese lancashire. Cheddar everyone loves cheese and wine cheese and wine mascarpone cheesy feet cheese slices paneer. Mozzarella boursin bavarian bergkase manchego cheese on toast cheddar port-salut taleggio. Fromage frais bocconcini bavarian bergkase danish fontina.';

    // DEBUG
    // echo count($data);
    // echo '<pre style="color: #666">';
    // print_r($data);
    // echo '</pre>';

    // holds valid job data
    $valid_jobs = array();

    // loop to extract job data
    foreach ($data as $job) :

        // DEBUG
        // echo $job['job_status']['label'] . '<br>';
        // continue;

        // if job type is not open, skip those jobs and continue
        if ($job_types == 'open') :
            if ($job['job_status']['label'] != 'Open') :
                continue;
            endif;
        endif;

        // debug description string length
        // echo strlen(strip_tags($job['job_description_text'])) . '<br>';
        // echo strlen($job['job_description_text']) . '<br>';

        // has description text
        if (strlen(strip_tags($job['job_description_text'])) > 50) :
            $clean_text = preg_replace('/<[^>]*>/', '', $job['job_description_text']);
            $description = strlen($clean_text) > 200 ? substr($clean_text, 0, 200) . '...' : $clean_text;
        // no description text, use placeholder text
        else :
            $description = substr($placeholder_text, 0, 200) . '...';
        endif;

        $valid_jobs[$job['id']] = [
            'title'       => strip_tags($job['name']),
            'description' => $description,
            'url'         => $job['application_form_url'],
        ];

    endforeach;

    // if $valid_jobs empty, log error to WP error log and bail
    if (empty($valid_jobs)) {
        error_log('Recruit CRM API error: No valid jobs found.');
        return;
    }

    // DEBUG
    // echo '<pre style="color: #666">';
    // print_r($valid_jobs);
    // echo '</pre>';
    // echo '<pre>';
    // print_r($_SERVER);
    // echo '</pre>';

    // Determine the number of job slides per slide page based on the device
    // $slidesPerPage = 4; // Default for desktop
    // $userAgent = $_SERVER['HTTP_USER_AGENT'];
    // if (strpos($userAgent, 'Mobile') !== false) {
    //     $slidesPerPage = 1; // For mobile
    // } elseif (strpos($userAgent, 'Tablet') !== false) {
    //     $slidesPerPage = 3; // For tablet
    // } // for width 1600px and below, 3 slides per page



    // Setup job description text
    // If description text is less than 300 chars, text remains as is
    // If description text is more than 300 chars, text is truncated to 300 chars and '...' is appended
    // If description text is less than 50 chars, placeholder text is used

    // foreach ($data as $job) :

    //     // has description text
    //     if (strlen(strip_tags($job['job_description_text'])) > 50) :
    //         $clean_text = preg_replace('/<[^>]*>/', '', $job['job_description_text']);
    //         $description = strlen($clean_text) > 300 ? substr($clean_text, 0, 300) . '...' : $clean_text;
    //     // no description text, use placeholder text
    //     else :
    //         $description = $placeholder_text;
    //     endif;

    //     $valid_jobs[$job['id']] = [
    //         'title'       => strip_tags($job['name']),
    //         'description' => $description,
    //         'url'         => $job['application_form_url'],
    //     ];

    // endforeach;

?>

    <div class="owl-carousel">
        <?php foreach ($valid_jobs as $job) : ?>
            <div class="hexagon">
                <div class="hexagon-content">
                    <img class="hexagon-slide-img-hover" src="<?php echo plugin_dir_url(__FILE__) . 'img/slide.hover.png' ?>" alt="hexagon slide hover image">
                    <img class="hexagon-slide-img" src="<?php echo plugin_dir_url(__FILE__) . 'img/slide.normal.png' ?>" alt="hexagon slide image">
                    <h2 class="title"><?php echo $job['title']; ?></h2>
                    <!-- <h2 class="title"><?php echo substr($job['title'], 0, 12); ?>...</h2>
                    <p class="description"><?php echo $job['description']  ?></p> -->
                    <a type="button" class="url" href="<?php echo $job['url']; ?>" title="click to view" target="_blank" rel="nofollow">
                        Read more
                        <img class="hexagon-rm-image" src="<?php echo plugin_dir_url(__FILE__) . 'img/readmore.arrow.png' ?>" alt="hexagon read more image">
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <style id="jobs-slider-css">
        .elementor-element.elementor-element-c6627f7.e-con-full.panel.e-flex.e-con.e-parent,
        .elementor-element.elementor-element-bf1d245.e-con-full.e-flex.e-con.e-parent {
            --padding-inline-start: 88px;
            --padding-inline-end: 88px;
            height: 1154px;
        }

        .elementor-element.elementor-element-2c060b98.e-con-full.panel.e-flex.e-con.e-parent {
            --padding-inline-start: 44px;
            --padding-inline-end: 44px;
            height: 1154px;
        }

        .elementor-element.elementor-element-4c8da6b.e-con-full.e-flex.e-con.e-parent {
            --padding-inline-start: 88px;
            --padding-inline-end: 88px;
            height: 969px;
        }

        .elementor-element.elementor-element-db09f5b.e-con-full.e-flex.e-con.e-child {
            --margin-block-start: 0;
        }

        .elementor-element.elementor-element-09b12f3.elementor-widget.elementor-widget-text-editor>div>h2 {
            font-size: 46px !important;
            margin-bottom: 45px;
        }

        .elementor-element.elementor-element-09b12f3.elementor-widget.elementor-widget-text-editor>div>p {
            width: 846px;
            margin: 0 auto 70px;
        }

        div.elementor-element.elementor-element-855aa6a.elementor-widget.elementor-widget-shortcode>div,
        .elementor-element.elementor-element-37bb18a.elementor-widget.elementor-widget-shortcode>div,
        .elementor-element.elementor-element-bea61a6.elementor-widget.elementor-widget-shortcode>div {
            margin: 0;
        }

        div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>p,
        .elementor-element.elementor-element-fe6fe2c.elementor-widget.elementor-widget-text-editor>div>p {
            width: 846px;
            margin-left: auto;
            margin-right: auto;
            line-height: 23.60px;
            word-wrap: break-word;
            font-weight: 400;
            color: #F5F5F5;
        }

        .elementor-element.elementor-element-fe6fe2c.elementor-widget.elementor-widget-text-editor>div>p {
            margin-bottom: 82px;
        }

        .elementor-element.elementor-element-21d09fe.elementor-align-center.elementor-widget.elementor-widget-button {
            margin-top: -20px;
            padding-top: 87px;
        }

        div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>h2,
        .elementor-element.elementor-element-1af1894.elementor-widget.elementor-widget-text-editor>div>h2 {
            font-size: 46px !important;
            color: #FEC7EB;
            font-weight: 400;
            line-height: 66.70px;
            word-wrap: break-word;
            margin-bottom: 45px;
        }

        div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div,
        .elementor-element.elementor-element-1af1894.elementor-widget.elementor-widget-text-editor>div {
            padding: 0;
            margin-bottom: 62px;
        }

        .elementor-element.elementor-element-bf1d245.e-con-full.e-flex.e-con.e-parent {
            --padding-block-start: 0;
            --margin-block-start: -80px;
            z-index: 1;
        }

        img.attachment-large.size-large.wp-image-1509 {
            z-index: 2;
            position: relative;
        }

        /* Owl Carousel container */
        /* .owl-carousel {
            display: flex;
            justify-content: center;
        } */

        /* Hexagon shape */
        .hexagon {
            position: relative;
            cursor: pointer;
            width: 407px;
            height: calc(407px * 1.116);
        }

        img.hexagon-slide-img,
        img.hexagon-slide-img-hover {
            width: 407px;
            height: calc(407px * 1.116);
            position: absolute;
            top: 0;
            left: 0;
            transition: all 0.3s ease-in-out;
        }

        .hexagon:hover img.hexagon-slide-img {
            opacity: 0;
        }

        img.hexagon-rm-image {
            width: 27.74px !important;
            position: relative;
            left: calc((210px - 27.74px)/2);
            top: 16px;
        }

        /* Adjust text size and styles as needed */
        .hexagon h2 {
            font-size: 32px;
            font-weight: 600;
            word-wrap: break-word;
            color: #901466;
            position: absolute;
            top: 100px;
            left: calc((407px - 300px)/2);
            width: 300px;
            text-align: center;
            line-height: 47px;
        }

        /* p */
        .hexagon p {
            font-size: 16px;
            word-wrap: break-word;
            text-align: center;
            font-weight: 400;
            position: absolute;
            color: #25143A;
            top: 170px;
            width: 333px;
            left: calc((407px - 333px)/2);
            line-height: 23.60px;
        }

        /* a */
        .hexagon a {
            width: 210px;
            font-size: 20px;
            color: #25143A;
            font-weight: 400;
            text-decoration: underline;
            line-height: 29.50px;
            word-wrap: break-word;
            position: absolute;
            top: 355px;
            text-align: center;
            left: calc((407px - 210px)/2);
        }

        /* .owl-carousel .owl-stage-outer {
            width: 1721px;
            left: 33px;
        } */

        /* .owl-carousel .owl-item {
            left: -33px;
        } */

        .owl-nav {
            display: block;
            width: 100%;
            text-align: center;
            position: relative;
            right: 2.5px;
            margin-top: 45px;
        }

        button.owl-prev {
            margin-right: 30px;
        }

        button.owl-prev,
        button.owl-next {
            position: relative;
            width: 39.62px;
        }

        button.owl-prev img,
        button.owl-next img {
            position: absolute;
            top: 0;
            left: 0;
            width: 39.62px;
            transition: all 0.3s ease-in-out;
        }

        img.arrow-left:hover,
        img.arrow-right:hover {
            opacity: 0;
        }

        html {
            overflow-x: hidden;
        }

        /* 1600 */
        @media screen and (max-width: 1600px) {
            .owl-stage-outer {
                width: 1286px;
                left: 68px;
            }
        }

        /* 1536 */
        @media screen and (max-width: 1536px) {
            .owl-stage-outer {
                left: 35px;
            }
        }

        /* 1440 */
        @media screen and (max-width: 1440px) {
            .owl-stage-outer {
                left: -10px;
            }
        }

        /* 1366 */
        @media screen and (max-width: 1366px) {
            .owl-stage-outer {
                left: -47px;
            }
        }

        /* 1280 */
        @media screen and (max-width: 1280px) {
            .owl-stage-outer {
                width: 850px;
                left: 124px;
            }

            .elementor-element.elementor-element-fe6fe2c.elementor-widget.elementor-widget-text-editor>div>p,
            .elementor-element.elementor-element-09b12f3.elementor-widget.elementor-widget-text-editor>div>p {
                width: 100% !important;
            }

            .elementor-597 .elementor-element.elementor-element-fe6fe2c>.elementor-widget-container,
            .elementor-647 .elementor-element.elementor-element-09b12f3>.elementor-widget-container {
                padding: 0;
            }

        }

        /* 1024 */
        @media screen and (max-width: 1024px) {
            .owl-stage-outer {
                left: -2px;
            }

        }

        /* 962 */
        @media screen and (max-width: 962px) {
            .owl-stage-outer {
                left: -32px;
            }

        }

        /* 810 */
        @media screen and (max-width: 810px) {
            .owl-stage-outer {
                left: 109px;
                width: 432px;
            }

            div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>p {
                width: 630px;
            }
        }

        /* 800 */
        @media screen and (max-width: 800px) {
            .owl-stage-outer {
                left: 105px;
            }

            div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>p {
                width: 620px;
            }
        }

        /* 768 */
        @media screen and (max-width: 768px) {
            .owl-stage-outer {
                left: 88px;
            }

            div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>p {
                width: 588px;
            }
        }

        /* 414 */
        @media screen and (max-width: 414px) {

            .owl-carousel .owl-item img {
                display: block;
                width: 100%;
            }

            .hexagon h2 {
                font-size: 26px;
                line-height: 146%;
                left: calc((328px - 300px)/2);
            }

            .hexagon a {
                top: 268px;
                left: calc((328px - 210px)/2);
            }

            img.hexagon-slide-img,
            img.hexagon-slide-img-hover,
            .hexagon {
                width: 328px;
                height: calc(328px * 1.116);
            }

            .owl-stage-outer {
                left: -42px;
                width: 350px;
            }

            .elementor-element.elementor-element-bea61a6.elementor-widget.elementor-widget-shortcode .owl-stage-outer {
                left: -12px;
            }

            div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>p,
            .elementor-element.elementor-element-fe6fe2c.elementor-widget.elementor-widget-text-editor>div>p,
            .elementor-element.elementor-element-09b12f3.elementor-widget.elementor-widget-text-editor>div>p,
            .elementor-element.elementor-element-7a40d6a7.elementor-widget.elementor-widget-text-editor>div>p {
                width: 328px;
                text-align: left !important;
                line-height: 23.6px !important;
            }

            .elementor-element.elementor-element-bf1d245.e-con-full.e-flex.e-con.e-parent,
            .elementor-element.elementor-element-c6627f7.e-con-full.panel.e-flex.e-con.e-parent,
            .elementor-element.elementor-element-4c8da6b.e-con-full.e-flex.e-con.e-parent,
            .elementor-element.elementor-element-2c060b98.e-con-full.panel.e-flex.e-con.e-parent {
                --padding-inline-start: 44px;
                --padding-inline-end: 44px;
                padding-block-start: 0px;
                padding-block-end: 0px;
            }

            div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>h2,
            .elementor-element.elementor-element-1af1894.elementor-widget.elementor-widget-text-editor>div>h2,
            .elementor-element.elementor-element-09b12f3.elementor-widget.elementor-widget-text-editor>div>h2,
            .elementor-element.elementor-element-6696138d.elementor-widget.elementor-widget-text-editor>div>h2 {
                font-size: 32px !important;
                line-height: 43.5px !important;
                text-align: left !important;

            }

            div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div,
            .elementor-element.elementor-element-1af1894.elementor-widget.elementor-widget-text-editor>div {
                padding: 0;
                margin-bottom: 36px;
            }

            .elementor-element.elementor-element-6696138d.elementor-widget.elementor-widget-text-editor h2 {
                padding-top: 83px;
            }

            .elementor-element.elementor-element-2a087c9e.elementor-align-center.elementor-widget.elementor-widget-button {
                margin-bottom: 95px;
            }
        }

        /* 393 */
        @media screen and (max-width: 393px) {
            .owl-stage-outer {
                left: -40px;
            }

            .elementor-element.elementor-element-bf1d245.e-con-full.e-flex.e-con.e-parent,
            .elementor-element.elementor-element-c6627f7.e-con-full.panel.e-flex.e-con.e-parent,
            .elementor-element.elementor-element-4c8da6b.e-con-full.e-flex.e-con.e-parent,
            .elementor-element.elementor-element-2c060b98.e-con-full.panel.e-flex.e-con.e-parent {
                --padding-inline-start: 31px;
                --padding-inline-end: 31px;
            }

            .owl-nav {
                right: -4px;
            }

            .elementor-element.elementor-element-2a087c9e.elementor-align-center.elementor-widget.elementor-widget-button {
                left: 5px;
            }
        }

        /* 390 */
        @media screen and (max-width: 390px) {}

        /* 360 */
        @media screen and (max-width: 360px) {

            .owl-stage-outer,
            .elementor-element.elementor-element-bea61a6.elementor-widget.elementor-widget-shortcode .owl-stage-outer {
                left: -56px;
            }
        }

        /* 328 */
        @media screen and (max-width: 328px) {
            .elementor-element.elementor-element-c6627f7.e-con-full.panel.e-flex.e-con.e-parent {
                --padding-inline-start: 15px;
                --padding-inline-end: 15px;
            }

            div.elementor-element.elementor-element-456f1b9.elementor-widget.elementor-widget-text-editor>div>p,
            .elementor-element.elementor-element-fe6fe2c.elementor-widget.elementor-widget-text-editor>div>p,
            .elementor-element.elementor-element-09b12f3.elementor-widget.elementor-widget-text-editor>div>p,
            .elementor-element.elementor-element-7a40d6a7.elementor-widget.elementor-widget-text-editor>div>p {
                width: 266px;
            }

            .owl-stage-outer,
            .elementor-element.elementor-element-bea61a6.elementor-widget.elementor-widget-shortcode .owl-stage-outer {
                left: -100px;
            }
        }
    </style>

    <script id="jobs-slider-js">
        jQuery(document).ready(function($) {

            // get current screen width
            var screenWidth = $(window).width();

            // setup slides per page
            if (screenWidth <= 414) {
                var slidesPerPage = 1;
            } else if (screenWidth >= 768) {
                var slidesPerPage = 2;
            } else if (screenWidth >= 1366) {
                var slidesPerPage = 3;
            } else if (screenWidth >= 1920) {
                var slidesPerPage = 4;
            } else if (screenWidth >= 2560) {
                var slidesPerPage = 5;
            }

            $(".owl-carousel").owlCarousel({
                items: slidesPerPage,
                loop: true,
                center: false,
                margin: 30,
                nav: true,
                navText: ["", ""],
                dots: false,
                autoWidth: true,

            });

            // hexagon on click find a element and open the link in a new tab
            $('.hexagon').on('click', function() {
                var url = $(this).find('.url').attr('href');
                window.open(url, '_blank');
            });

            // log current window width on resize
            $(window).resize(function() {
                console.log($(window).width());
            });

            // append hover images to nav buttons
            $('.owl-prev').append('<img class="arrow-left-hover" src="<?php echo plugin_dir_url(__FILE__) . 'img/nav.hover.left.png' ?>" alt="hexagon nav image left hover">');
            $('.owl-next').append('<img class="arrow-right-hover" src="<?php echo plugin_dir_url(__FILE__) . 'img/nav.hover.right.png' ?>" alt="hexagon nav image right hover">');

            // append normal images to nav buttons
            $('.owl-prev').append('<img class="arrow-left" src="<?php echo plugin_dir_url(__FILE__) . 'img/nav.normal.left.png' ?>" alt="hexagon nav image left">');
            $('.owl-next').append('<img class="arrow-right" src="<?php echo plugin_dir_url(__FILE__) . 'img/nav.normal.right.png' ?>" alt="hexagon nav image right">');

            // Hide owl navigation if job count is equal to owl item count for a given screen width
            var jobCount = '<?php echo count($valid_jobs); ?>';

            console.log('job count: ' + jobCount);
            console.log('owl item count: ' + slidesPerPage);

            // debug
            // jobCount = 2;

            if (jobCount == slidesPerPage) {
                setTimeout(() => {
                    $('.owl-nav').hide();
                }, 500);
            }

            // calc absolute position from top of .hexagon for child element .title, using .hexagon height and .title height

            $('.hexagon').each(function(index, element) {
                // element == this
                var hexagonHeight = $(this).height();
                var titleHeight = $(this).find('.title').height();
                var titleTop = (hexagonHeight - titleHeight) / 2.2;
                $(this).find('.title').css('top', titleTop);

                console.log('hexagon height: ' + hexagonHeight);
                console.log('title height: ' + titleHeight);
                console.log('title top: ' + titleTop);
            });


        });
    </script>



<?php
}


?>