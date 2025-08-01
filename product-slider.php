<?php
/**
 * Plugin Name: WooCommerce Latest Products Slider
 * Description: Displays the latest WooCommerce products using Swiper.js in a responsive slider.
 * Version: 1.0
 * Author: WPDESIN LAB
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>WooCommerce Latest Products Slider requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

// Define plugin URL for asset loading
define('WC_SLIDER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Enqueue styles and scripts
add_action('wp_enqueue_scripts', 'wc_slider_enqueue_assets');
function wc_slider_enqueue_assets() {
    // Swiper CSS from CDN
    wp_enqueue_style(
        'swiper-css',
        'https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css',
        array(),
        '8.4.5'
    );

    // Normalize CSS

    // Custom style.css from plugin folder
    wp_enqueue_style(
        'wc-slider-style',
        WC_SLIDER_PLUGIN_URL . 'style.css',
        array('swiper-css'),
        '1.0'
    );

    // Swiper JS from CDN
    wp_enqueue_script(
        'swiper-js',
        'https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js',
        array(),
        '8.4.5',
        true
    );

    // Custom script.js from plugin folder
    wp_enqueue_script(
        'wc-slider-script',
        WC_SLIDER_PLUGIN_URL . 'script.js',
        array('swiper-js'),
        '1.0',
        true
    );
}

// Shortcode to display the slider
// Register multiple shortcodes with dynamic parameters

add_shortcode('latest_products_slider', 'wc_slider_shortcode');
add_shortcode('featured_products_slider', 'wc_slider_shortcode');
add_shortcode('products_slider', 'wc_slider_shortcode');

function wc_slider_shortcode($atts) {
    // 🔴 ADD THIS RIGHT HERE — FIRST LINE IN THE FUNCTION
    if (is_admin()) {
        return ''; // Or use a placeholder (see below)
    }

    // Default attributes
    $atts = shortcode_atts(array(
        'limit'     => -1,
        'category'  => '', // Comma-separated slugs: clothing,accessories
        'tag'       => '', // Comma-separated tags
        'orderby'   => 'date', // Options: date, title, price, popularity, rand
        'order'     => 'DESC',
        'type'      => '', // 'featured', 'onsale' — or inferred from shortcode
    ), $atts, 'products_slider');

    // Detect which shortcode was used
    $shortcode = do_shortcode_tag_to_type($atts);

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval($atts['limit']),
        'post_status'    => 'publish',
        'tax_query'      => array('relation' => 'AND'),
    );

    // Order handling
    switch ($atts['orderby']) {
        case 'price':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_price';
            break;
        case 'popularity':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'total_sales';
            break;
        case 'rand':
            $args['orderby'] = 'rand';
            break;
        case 'title':
            $args['orderby'] = 'title';
            break;
        default:
            $args['orderby'] = 'date';
    }
    $args['order'] = $atts['order'];

    // Filter by category
    if (!empty($atts['category'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => explode(',', $atts['category']),
            'operator' => 'IN',
        );
    }

    // Filter by tag
    if (!empty($atts['tag'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_tag',
            'field'    => 'slug',
            'terms'    => explode(',', $atts['tag']),
            'operator' => 'IN',
        );
    }

    // Featured products: two ways — via shortcode name or type="featured"
    $is_featured = $atts['type'] === 'featured' || $shortcode === 'featured';

    if ($is_featured) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => array('featured'),
            'operator' => 'IN',
        );
    }

    // On-sale products
    if ($atts['type'] === 'onsale') {
        $args['post__in'] = wc_get_product_ids_on_sale();
    }

    // Visibility: exclude from catalog/search
    $args['tax_query'][] = array(
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => array('exclude-from-catalog', 'exclude-from-search'),
        'operator' => 'NOT IN',
    );

    $loop = new WP_Query($args);

    ob_start();
    ?>

    <div class="outer">
        <div class="container-sl">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php if ($loop->have_posts()) : ?>
                        <?php while ($loop->have_posts()) : $loop->the_post(); ?>
                            <?php
                            $product = wc_get_product(get_the_ID());
                            $image_id = get_post_thumbnail_id();
                            $image_src = $image_id ? 
                                wp_get_attachment_image_src($image_id, 'full')[0] : 
                                wc_placeholder_img_src('full');
                            $name = get_the_title();
                            $price = $product->get_price_html();
                            ?>
                            <div class="swiper-slide swiper-slide-active">
                                <img src="<?php echo esc_url($image_src); ?>" alt="<?php echo esc_attr($name); ?>" />
                                <div class="info">
                                    <h4 class="name">
    <a href="<?php echo esc_url(get_permalink()); ?>" style="color: inherit; text-decoration: none;">
        <?php echo esc_html($name); ?>
    </a>
</h4>
                                    <span class="type"><?php echo $price ? $price : ''; ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        <?php wp_reset_postdata(); ?>
                    <?php else : ?>
                        <div class="swiper-slide">
                            <p><?php esc_html_e('No products found.', 'wc-slider'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <div class="swiper-pagination"></div>

                <!-- Navigation buttons -->
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

// Helper: Detect which shortcode was used
function do_shortcode_tag_to_type($atts) {
    global $shortcode_tags;

    // We can't directly get the tag, so we use a filter
    static $last_tag;
    if (!$last_tag) {
        add_filter('do_shortcode_tag', function ($output, $tag) use (&$last_tag) {
            $last_tag = $tag;
            return $output;
        }, 10, 2);
    }

    $tag = $last_tag;
    $last_tag = null; // Reset

    if ($tag === 'featured_products_slider') {
        return 'featured';
    }
    return '';
}






// Add submenu under Settings
add_action('admin_menu', 'wc_slider_add_instructions_page');

function wc_slider_add_instructions_page() {
    add_submenu_page(
        'options-general.php',           // Parent: Settings menu
        'Product Slider Shortcodes',     // Page title
        'Slider Instructions',           // Menu title (shown in Settings menu)
        'manage_options',                // Required capability (admin only)
        'wc-slider-shortcodes',          // Menu slug (unique)
        'wc_slider_instructions_page_content' // Callback function
    );
}

// Output the instructions page
function wc_slider_instructions_page_content() {
    ?>
    <div class="wrap">
        <h1>WooCommerce Product Slider Shortcodes</h1>
        <p style="font-size: 1.1em; color: #555;">
            Use these shortcodes to display product sliders anywhere on your site: pages, posts, widgets (if shortcode enabled), or templates.
        </p>

        <hr>

        <h2>Available Shortcodes</h2>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><strong>Shortcode</strong></th>
                    <th><strong>Description</strong></th>
                    <th><strong>Example</strong></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[latest_products_slider]</code></td>
                    <td>Show the latest published products.</td>
                    <td>
                        <input type="text" readonly value="[latest_products_slider limit=&quot;6&quot; orderby=&quot;date&quot;]" class="large-text">
                    </td>
                </tr>
                <tr>
                    <td><code>[featured_products_slider]</code></td>
                    <td>Show only <strong>featured</strong> products (mark as featured in product edit).</td>
                    <td>
                        <input type="text" readonly value="[featured_products_slider limit=&quot;4&quot;]" class="large-text">
                    </td>
                </tr>
                <tr>
                    <td><code>[products_slider category="..."]</code></td>
                    <td>Show products from specific categories (use slugs).</td>
                    <td>
                        <input type="text" readonly value="[products_slider category=&quot;clothing,accessories&quot; limit=&quot;8&quot;]" class="large-text">
                    </td>
                </tr>
                <tr>
                    <td><code>[products_slider tag="..."]</code></td>
                    <td>Show products with specific tags.</td>
                    <td>
                        <input type="text" readonly value="[products_slider tag=&quot;sale,summer&quot;]" class="large-text">
                    </td>
                </tr>
                <tr>
                    <td><code>[products_slider type="onsale"]</code></td>
                    <td>Show only products currently on sale.</td>
                    <td>
                        <input type="text" readonly value="[products_slider type=&quot;onsale&quot; limit=&quot;6&quot;]" class="large-text">
                    </td>
                </tr>
            </tbody>
        </table>

        <h2>Parameters (Attributes)</h2>
        <p>You can customize any shortcode with these attributes:</p>
        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th>Attribute</th>
                    <th>Options / Format</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>limit</code></td>
                    <td><code>5</code> (number)</td>
                    <td>Number of products to show. Default: 5</td>
                </tr>
                <tr>
                    <td><code>orderby</code></td>
                    <td><code>date</code>, <code>title</code>, <code>price</code>, <code>popularity</code>, <code>rand</code></td>
                    <td>Sort order of products.</td>
                </tr>
                <tr>
                    <td><code>order</code></td>
                    <td><code>DESC</code> (newest first), <code>ASC</code> (oldest first)</td>
                    <td>Direction of order.</td>
                </tr>
                <tr>
                    <td><code>category</code></td>
                    <td><code>shoes</code>, <code>clothing,electronics</code></td>
                    <td>Product category slugs (find in Products → Categories).</td>
                </tr>
                <tr>
                    <td><code>tag</code></td>
                    <td><code>sale</code>, <code>summer,vip</code></td>
                    <td>Product tag slugs.</td>
                </tr>
                <tr>
                    <td><code>type</code></td>
                    <td><code>featured</code>, <code>onsale</code></td>
                    <td>Alternative to using specific shortcodes.</td>
                </tr>
            </tbody>
        </table>

        <h2>How to Use</h2>
        <ol style="font-size: 1.1em; line-height: 1.8;">
            <li>Edit any <strong>Page or Post</strong> in WordPress.</li>
            <li>Paste the shortcode into the editor (Classic or Block Editor in "Shortcode" block).</li>
            <li>Click <strong>Update/Publish</strong>.</li>
            <li>View the page — your product slider will appear!</li>
        </ol>

        <h2>Pro Tips</h2>
        <ul style="font-size: 1.1em; line-height: 1.8;">
            <li>Make a product <strong>featured</strong> by editing it → <strong>Product Data → Linked Products → "Featured" checkbox</strong>.</li>
            <li>Use <strong>SiteOrigin Widgets</strong>, <strong>Elementor</strong>, or <strong>Text blocks</strong> to add shortcodes in layouts.</li>
            <li>All sliders are responsive and use Swiper.js for smooth touch/carousel effects.</li>
        </ul>

        <hr>
        <p><em>Plugin: WooCommerce Latest Products Slider | Need help? Check shortcode syntax above.</em></p>
    </div>

    <!-- Make input fields clickable to copy -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = document.querySelectorAll('input[readonly]');
            inputs.forEach(input => {
                input.addEventListener('click', function () {
                    this.select();
                    document.execCommand('copy');
                    alert('Shortcode copied: ' + this.value);
                });
                input.style.cursor = 'pointer';
                input.title = 'Click to copy';
            });
        });
    </script>

    <style>
        .wrap h1, .wrap h2 { color: #0073aa; }
        input[readonly] {
            font-family: monospace;
            padding: 10px;
            border: 1px dashed #ddd;
            background: #f9f9f9;
            border-radius: 4px;
            margin: 5px 0;
        }
        input[readonly]:hover {
            border-color: #0073aa;
            background: #f1faff;
        }
    </style>
    <?php
}



