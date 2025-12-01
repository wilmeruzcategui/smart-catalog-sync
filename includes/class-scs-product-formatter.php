<?php
/**
 * Product Formatter - Formats WooCommerce products to AI-friendly JSON
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCS_Product_Formatter {

    private $settings;

    public function __construct() {
        $this->settings = get_option('scs_settings');
    }

    /**
     * Format all products into AI-optimized JSON structure
     */
    public function format_all_products() {
        $products = $this->get_all_products();
        $formatted_products = array();

        foreach ($products as $product) {
            $formatted = $this->format_product($product);
            if ($formatted) {
                $formatted_products[] = $formatted;
            }
        }

        return array(
            'sync_date' => current_time('c'),
            'store_info' => array(
                'name' => get_bloginfo('name'),
                'url' => get_site_url(),
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
            ),
            'total_products' => count($formatted_products),
            'products' => $formatted_products,
        );
    }

    /**
     * Get all published products
     */
    private function get_all_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Format a single product
     */
    private function format_product($post) {
        $product = wc_get_product($post->ID);

        if (!$product) {
            return null;
        }

        $formatted = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'sku' => $product->get_sku() ?: '',
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'permalink' => get_permalink($product->get_id()),
        );

        // Pricing
        $formatted['price'] = (float) $product->get_price();
        $formatted['regular_price'] = (float) $product->get_regular_price();
        $formatted['sale_price'] = $product->get_sale_price() ? (float) $product->get_sale_price() : null;
        $formatted['on_sale'] = $product->is_on_sale();

        // Stock
        $formatted['stock_status'] = $product->get_stock_status();
        $formatted['stock_quantity'] = $product->get_stock_quantity();
        $formatted['manage_stock'] = $product->get_manage_stock();
        $formatted['in_stock'] = $product->is_in_stock();
        $formatted['backorders_allowed'] = $product->backorders_allowed();

        // Descriptions
        $formatted['description'] = wp_strip_all_tags($product->get_description());
        $formatted['short_description'] = wp_strip_all_tags($product->get_short_description());

        // Categories
        if ($this->settings['include_categories']) {
            $formatted['categories'] = $this->get_product_categories($product->get_id());
            $formatted['tags'] = $this->get_product_tags($product->get_id());
        }

        // Images
        if ($this->settings['include_images']) {
            $formatted['images'] = $this->get_product_images($product);
            $formatted['featured_image'] = wp_get_attachment_url($product->get_image_id());
        }

        // Attributes
        $formatted['attributes'] = $this->get_product_attributes($product);

        // Variations
        if ($this->settings['include_variations'] && $product->is_type('variable')) {
            $formatted['variations'] = $this->get_product_variations($product);
        }

        // Additional useful data for AI
        $formatted['weight'] = $product->get_weight();
        $formatted['dimensions'] = array(
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
        );

        $formatted['rating_count'] = $product->get_rating_count();
        $formatted['average_rating'] = (float) $product->get_average_rating();
        $formatted['total_sales'] = (int) $product->get_total_sales();

        return $formatted;
    }

    /**
     * Get product categories
     */
    private function get_product_categories($product_id) {
        $terms = get_the_terms($product_id, 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        return array_map(function($term) {
            return array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }, $terms);
    }

    /**
     * Get product tags
     */
    private function get_product_tags($product_id) {
        $terms = get_the_terms($product_id, 'product_tag');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        return array_map(function($term) {
            return $term->name;
        }, $terms);
    }

    /**
     * Get product images
     */
    private function get_product_images($product) {
        $image_ids = $product->get_gallery_image_ids();
        $images = array();

        // Add featured image first
        $featured_image = wp_get_attachment_url($product->get_image_id());
        if ($featured_image) {
            $images[] = $featured_image;
        }

        // Add gallery images
        foreach ($image_ids as $image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $images[] = $image_url;
            }
        }

        return $images;
    }

    /**
     * Get product attributes
     */
    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();

        foreach ($product_attributes as $attribute) {
            $attribute_data = array(
                'name' => wc_attribute_label($attribute->get_name()),
                'visible' => $attribute->get_visible(),
                'variation' => $attribute->get_variation(),
            );

            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                $attribute_data['options'] = array_map(function($term) {
                    return $term->name;
                }, $terms);
            } else {
                $attribute_data['options'] = $attribute->get_options();
            }

            $attributes[] = $attribute_data;
        }

        return $attributes;
    }

    /**
     * Get product variations
     */
    private function get_product_variations($product) {
        $variations = array();
        $variation_ids = $product->get_children();

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }

            $variations[] = array(
                'id' => $variation->get_id(),
                'sku' => $variation->get_sku(),
                'price' => (float) $variation->get_price(),
                'regular_price' => (float) $variation->get_regular_price(),
                'sale_price' => $variation->get_sale_price() ? (float) $variation->get_sale_price() : null,
                'stock_quantity' => $variation->get_stock_quantity(),
                'stock_status' => $variation->get_stock_status(),
                'in_stock' => $variation->is_in_stock(),
                'attributes' => $variation->get_variation_attributes(),
                'image' => wp_get_attachment_url($variation->get_image_id()),
            );
        }

        return $variations;
    }
}
