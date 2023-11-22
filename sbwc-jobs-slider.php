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
    add_shortcode('jobs_carousel', 'sbwc_jobs_slider_shortcode');
}

add_action('init', 'sbwc_jobs_slider_plugin_init');

// Add the admin page
function sbwc_jobs_slider_add_admin_page()
{
    add_menu_page(
        'SBWC Jobs Slider',
        'SBWC Jobs Slider',
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

            $valid_jobs[$job['id']] = [
                'title'       => strip_tags($job['name']),
                'description' => $clean_text,
                // 'url'         => $job['resource_url'],
                'url'         => $job['application_form_url'],
            ];

        // no description text, use placeholder text
        else :
            $valid_jobs[$job['id']] = [
                'title'       => strip_tags($job['name']),
                'description' => $placeholder_text,
                // 'url'         => $job['resource_url'],
                'url'         => $job['application_form_url'],
            ];
        endif;

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

    // Add the Owl Carousel initialization code after the existing code

    // Determine the number of job slides per slide page based on the device
    $slidesPerPage = 4; // Default for desktop
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($userAgent, 'Mobile') !== false) {
        $slidesPerPage = 1; // For mobile
    } elseif (strpos($userAgent, 'Tablet') !== false) {
        $slidesPerPage = 3; // For tablet
    }



?>

    <div class="owl-carousel">
        <?php foreach ($valid_jobs as $job) : ?>
            <div class="hexagon">
                <div class="hexagon-content">
                    <h2 class="title"><?php echo $job['title']; ?></h2>
                    <p class="description"><?php echo strlen($job['description'] < 200) ? substr($job['description'], 0, 200) . '...' : substr($placeholder_text, 0, 200) . '...'; ?></p>
                    <a type="button" class="url" href="<?php echo $job['url']; ?>" title="click to view" target="_blank" rel="nofollow">
                        Read more
                    </a>
                    <span class="arrow-right"></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <style>
        /* Owl Carousel container */
        .owl-carousel {
            display: flex;
            justify-content: center;
        }

        /* Hexagon shape */
        .hexagon {
            background: transparent;
            background-image: url(<?php echo plugin_dir_url(__FILE__) . 'img/slide.normal.png' ?>);
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            width: 460px;
            height: 511px;
            position: relative;
            transition: all 0.3s ease-in-out;
            cursor: pointer;
        }

        .hexagon:hover {
            background-image: url('<?php echo plugin_dir_url(__FILE__) . 'img/slide.hover.png' ?>');
            transition: all 0.3s ease-in-out;
        }

        /* .arrow-right display as block and use background image readmore.arrow.png */
        .arrow-right {
            display: block;
            background-image: url('<?php echo plugin_dir_url(__FILE__) . 'img/readmore.arrow.png' ?>');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            width: 32px;
            height: 35px;
            position: absolute;
            bottom: 6%;
            right: 46.5%;
            transition: all 0.3s ease-in-out;
        }

        /* Adjust text size and styles as needed */
        .hexagon h2 {
            font-size: 25px;
            margin-bottom: 5px;
            position: absolute;
            top: 22%;
            width: 80%;
            line-height: 1.5;
            text-align: center;
            left: 10%;
            color: #901466;
            font-weight: 600;
        }

        /* p */
        .hexagon p {
            font-size: 18px;
            margin-bottom: 5px;
            position: absolute;
            top: 40%;
            max-width: 80%;
            left: 10%;
            line-height: 1.5;
            text-align: center;
            color: #111;
        }

        /* a */
        .hexagon a {
            text-decoration: none;
            color: #ffcc00;
            display: block;
            position: absolute;
            bottom: 17%;
            width: 80%;
            left: 10%;
            text-align: center;
            color: #111;
            font-size: 20px;
            font-weight: 500;
        }

        .owl-carousel {
            margin-top: 30px;
        }

        .owl-nav {
            text-align: center;
            position: relative;
            right: 10px;
            margin: 60px 0;
        }

        button.owl-prev {
            position: relative !important;
            right: 15px;
            background-image: url('<?php echo plugin_dir_url(__FILE__) . 'img/nav.normal.left.png' ?>') !important;
            background-repeat: no-repeat !important;
            background-size: cover !important;
            background-position: center !important;
            width: 40px;
            height: 43px;
            transition: all 0.3s ease-in-out;
        }

        button.owl-prev:hover {
            background-image: url('<?php echo plugin_dir_url(__FILE__) . 'img/nav.hover.left.png' ?>') !important;
            transition: all 0.3s ease-in-out;
        }

        button.owl-next {
            position: relative !important;
            left: 15px;
            background-image: url('<?php echo plugin_dir_url(__FILE__) . 'img/nav.normal.right.png' ?>') !important;
            background-repeat: no-repeat !important;
            background-size: cover !important;
            background-position: center !important;
            width: 40px;
            height: 43px;
            transition: all 0.3s ease-in-out;
        }

        button.owl-next:hover {
            background-image: url('<?php echo plugin_dir_url(__FILE__) . 'img/nav.hover.right.png' ?>') !important;
            transition: all 0.3s ease-in-out;
        }

        .elementor-element.elementor-element-21d09fe.elementor-align-center.elementor-widget.elementor-widget-button {
            position: relative;
            right: 8px;
        }

        html {
            overflow-x: hidden;
        }

        /* media max width 1440px */
        @media (max-width: 1440px) {
            .hexagon {
                width: 405px;
                height: 450px;
            }

            .hexagon h2 {
                font-size: 22px;
            }

            .hexagon p {
                font-size: 16px;
            }

            .hexagon a {
                font-size: 18px;
            }

            .owl-nav {
                margin: 40px 0;
            }

            button.owl-prev {
                width: 35px;
                height: 38px;
            }

            button.owl-next {
                width: 35px;
                height: 38px;
            }
        }

        /* media max width 1366px */
        @media (max-width: 1366px) {
            .hexagon {
                width: 374px;
                height: 415px;
            }

            .hexagon h2 {
                font-size: 20px;
            }

            .hexagon p {
                font-size: 14px;
            }

            .hexagon a {
                font-size: 16px;
            }

            .owl-nav {
                margin: 30px 0;
            }

            button.owl-prev {
                width: 30px;
                height: 33px;
            }

            button.owl-next {
                width: 30px;
                height: 33px;
            }
        }

        /* media max width 1280px */
        @media (max-width: 1280px) {
            .hexagon {
                width: 348px;
                height: 386px;
            }

            .hexagon h2 {
                font-size: 18px;
            }

            .hexagon p {
                font-size: 12px;
            }

            .hexagon a {
                font-size: 14px;
            }

            .owl-nav {
                margin: 20px 0;
            }

            button.owl-prev {
                width: 25px;
                height: 28px;
            }

            button.owl-next {
                width: 25px;
                height: 28px;
            }
        }

        /* media max width 1024px */
        @media (max-width: 1024px) {
            .hexagon {
                width: 261px;
                height: 290px;
            }

            .hexagon h2 {
                font-size: 16px;
            }

            .hexagon p {
                font-size: 10px;
            }

            .hexagon a {
                font-size: 12px;
            }

            .owl-nav {
                margin: 10px 0;
            }

            button.owl-prev {
                width: 21px;
                height: 23px;
            }

            button.owl-next {
                width: 21px;
                height: 23px;
            }

            .arrow-right {
                width: 27px;
                height: 29px;
                bottom: 5%;
                right: 45.5%;
            }

            .owl-nav {
                margin: 30px 0;
            }
        }

        /* media max width 768px */
        @media (max-width: 768px) {
            .hexagon {
                width: 287px;
                height: 319px;
            }

            .hexagon h2 {
                font-size: 16px;
            }

            .hexagon p {
                font-size: 11px;
            }

            .hexagon a {
                font-size: 12px;
            }

            .owl-nav {
                margin: 10px 0;
            }

            button.owl-prev {
                width: 17px;
                height: 19px;
            }

            button.owl-next {
                width: 17px;
                height: 19px;
            }

            .arrow-right {
                width: 22px;
                height: 24px;
                bottom: 5%;
                right: 45.5%;
            }

            .owl-nav {
                margin: 20px 0;
                right: 5px;
            }
        }

        /* media max width 450px */
        @media (max-width: 450px) {
            .hexagon {
                width: 284px;
                height: 316px;
            }

            .owl-nav {
                margin: 20px 0;
            }

            button.owl-prev {
                width: 25px;
                height: 27px;
            }

            button.owl-next {
                width: 25px;
                height: 27px;
            }

            .arrow-right {
                width: 19px;
                height: 21px;
                bottom: 5%;
                right: 46.5%;
            }

            .owl-nav {
                margin: 20px 0;
                right: 5px;
            }
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            $(".owl-carousel").owlCarousel({
                items: <?php echo $slidesPerPage; ?>,
                loop: true,
                center: false,
                margin: 10,
                nav: true,
                navText: ["", ""],
                dots: false,
                responsive: {
                    0: {
                        items: 1
                    },
                    450: {
                        items: 1
                    },
                    768: {
                        items: 2
                    },
                    992: {
                        items: 3
                    },
                    1920: {
                        items: 4
                    },
                    2560: {
                        items: 5
                    }
                }
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

        });
    </script>



<?php
}


?>