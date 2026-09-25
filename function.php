<?php

/**
 * The Liquid Themes Hub Theme  (PERFORMANCE-OPTIMIZED)
 *
 * Note: Do not add any custom code here. Please use a child theme so that your customizations aren't lost during updates.
 * http://codex.wordpress.org/Child_Themes
 *
 * @link https://codex.wordpress.org/Theme_Development
 * @link https://codex.wordpress.org/Child_Themes
 *
 * Text Domain: 'hub'
 * Domain Path: /languages/
 *
 * ---------------------------------------------------------------------------
 * OPTIMIZATION SUMMARY (no functions removed, behaviour unchanged):
 *  1. Auth token is now cached (transient + per-request static) instead of a
 *     fresh POST on every call.
 *  2. New pf_authenticated_get() wrapper: 1 place for GET calls, auto-retries
 *     once on 401 (token expiry) so caching is safe.
 *  3. pf_get_location_full_data() cached (static + transient).
 *  4. pf_get_descendant_location_ids() cached.
 *  5. pf_get_properties() responses short-cached (5 min).
 *  6. Single property fetched ONCE per page via pf_get_listing_by_ref()
 *     (header + details + <title> now share one request instead of 3).
 *  7. pf_ajax_load_locations() uses batch location fetch (no N+1 calls).
 *  8. Listing images get loading="lazy" + decoding="async".
 *  9. error_log() guarded behind WP_DEBUG.
 * ---------------------------------------------------------------------------
 */

// Starting The Engine / Load the Liquid Framework ----------------
include_once( get_template_directory() . '/liquid/liquid-init.php' );


/* =========================================================================
 *  CORE / CACHING HELPERS
 * ========================================================================= */

// 🔑 Get Auth Token (CACHED: per-request static + transient ~45 min)
function pf_get_auth_token( $force_refresh = false ) {
    static $runtime_token = null;

    // Same request, already have it -> return immediately
    if ( ! $force_refresh && $runtime_token !== null ) {
        return $runtime_token;
    }

    // Cross-request cache
    if ( ! $force_refresh ) {
        $cached = get_transient( 'pf_auth_token' );
        if ( ! empty( $cached ) ) {
            $runtime_token = $cached;
            return $cached;
        }
    }

    $api_key    = 'LQjDh.7B5FI3axFEg5SiPx4EBw7ZPuq5Llab6kSX';
    $api_secret = 'hF0eMSfN2xmDtRTfsXPdyK2wrQ5UcG1Q';

    $url = 'https://atlas.propertyfinder.com/v1/auth/token';
    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode(['apiKey' => $api_key, 'apiSecret' => $api_secret]),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) return false;

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $token = $data['accessToken'] ?? false;

    if ( $token ) {
        // Cache for 45 minutes (tokens typically live ~60 min).
        set_transient( 'pf_auth_token', $token, 45 * MINUTE_IN_SECONDS );
        $runtime_token = $token;
    }

    return $token;
}

// 🔗 Centralised authenticated GET with auto token-refresh on 401.
// Returns decoded array (or null). Keeps the cached token safe.
function pf_authenticated_get( $url, $extra_headers = [], $timeout = 20 ) {
    $token = pf_get_auth_token();
    if ( ! $token ) return null;

    $headers = array_merge([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
    ], $extra_headers);

    $response = wp_remote_get($url, [ 'headers' => $headers, 'timeout' => $timeout ]);
    if ( is_wp_error($response) ) return null;

    // If the cached token expired right at the boundary, refresh once and retry.
    if ( (int) wp_remote_retrieve_response_code($response) === 401 ) {
        $token = pf_get_auth_token( true );
        if ( ! $token ) return null;
        $headers['Authorization'] = 'Bearer ' . $token;
        $response = wp_remote_get($url, [ 'headers' => $headers, 'timeout' => $timeout ]);
        if ( is_wp_error($response) ) return null;
    }

    $body = wp_remote_retrieve_body($response);
    if ( $body === '' ) return null;

    return json_decode($body, true);
}

// 🏷️ Fetch a single listing by reference ONCE per request (memoized + short transient).
// Used by the property header, details, and <title> so they share one API call.
function pf_get_listing_by_ref( $ref ) {
    $ref = (string) $ref;
    if ( $ref === '' ) return null;

    static $memo = [];
    if ( array_key_exists( $ref, $memo ) ) {
        return $memo[ $ref ];
    }

    $cache_key = 'pf_listing_ref_' . md5( $ref );
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) {
        $memo[ $ref ] = $cached ?: null;
        return $memo[ $ref ];
    }

    $api_url = 'https://atlas.propertyfinder.com/v1/listings?filter[reference]=' . urlencode($ref);
    $data    = pf_authenticated_get( $api_url, [], 30 );

    $property = $data['results'][0] ?? null;

    // Cache for 5 minutes (empty result cached briefly as empty string to avoid hammering).
    set_transient( $cache_key, $property ? $property : '', 5 * MINUTE_IN_SECONDS );

    $memo[ $ref ] = $property;
    return $property;
}


/* =========================================================================
 *  LOCATION HELPERS
 * ========================================================================= */

// 🔑 Get Full Location Data by ID (CACHED: static + transient)
function pf_get_location_full_data($location_id) {
    if (!$location_id || $location_id == 0) return null;

    static $memo = [];
    if ( array_key_exists( (string) $location_id, $memo ) ) {
        return $memo[ (string) $location_id ];
    }

    $cache_key = 'pf_loc_full_' . $location_id;
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) {
        $memo[ (string) $location_id ] = $cached ?: null;
        return $memo[ (string) $location_id ];
    }

    $url  = 'https://atlas.propertyfinder.com/v1/locations?filter[id]=' . $location_id;
    $data = pf_authenticated_get( $url, [ 'Accept-Language' => 'en' ], 20 );

    $result = ( ! empty($data['data'][0]) ) ? $data['data'][0] : null;

    // Cache for 6 hours (locations rarely change).
    set_transient( $cache_key, $result ? $result : '', 6 * HOUR_IN_SECONDS );

    $memo[ (string) $location_id ] = $result;
    return $result;
}

// 🔑 Get Location Details by ID (returns formatted string for display)
function pf_get_location_details($location_id) {
    $location_data = pf_get_location_full_data($location_id);
    
    if (!$location_data) return '';
    
    $name = $location_data['name'] ?? '';
    
    if (!empty($location_data['tree'])) {
        $tree_names = array_map(function($t) { return $t['name'] ?? ''; }, $location_data['tree']);
        $tree_names = array_reverse($tree_names);
        $name = implode(', ', $tree_names);
    }
    
    return $name;
}


// 👇 Fetch all descendant location IDs under a parent (by ID) using the new filter[parent] feature
// (CACHED for 6 hours)
function pf_get_descendant_location_ids($parent_id, $types = ['TOWER','SUBCOMMUNITY','PROJECT','COMPOUND','AREA','DISTRICT']) {

    $cache_key = 'pf_desc_ids_' . $parent_id . '_' . md5( implode(',', $types) );
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $parent = pf_get_location_full_data($parent_id);
    if (!$parent) return [];

    // Build "Dubai, Dubai Hills Estate"
    $tree = $parent['tree'] ?? [];
    $names = array_map(fn($t) => $t['name'] ?? '', $tree);
    $parent_name = $parent['name'] ?? '';
    $cut = array_search($parent_name, $names);
    if ($cut !== false) $names = array_slice($names, 0, $cut + 1);
    $parent_path = implode(', ', array_filter($names));

    $token = pf_get_auth_token();
    if (!$token || !$parent_path) return [];

    // ✅ REQUIRED by the API; use parent name to keep results relevant
    $search = strlen($parent_name) >= 2 ? $parent_name : 'aa';

    $page = 1; $perPage = 100; $ids = [];
    do {
        $url = add_query_arg([
            'perPage'        => $perPage,
            'page'           => $page,
            'search'         => $search,                 // <-- REQUIRED
            'filter[parent]' => $parent_path,            // disambiguate the hierarchy
            'filter[type]'   => implode(',', $types),    // fetch leaf-ish nodes
        ], 'https://atlas.propertyfinder.com/v1/locations');

        $body = pf_authenticated_get( $url, [ 'Accept-Language' => 'en' ], 20 );
        if ( $body === null ) break;

        foreach (($body['data'] ?? []) as $loc) {
            if (!empty($loc['id'])) $ids[] = (int)$loc['id'];
        }
        $pagination = $body['pagination'] ?? [];
        $page++;
    } while (!empty($pagination['nextPage']));

    // Unique & return (optionally include parent id as a fallback)
    $ids = array_values(array_unique($ids));
    if (empty($ids)) $ids[] = (int)$parent_id; // optional fallback

    // Cache for 6 hours.
    set_transient( $cache_key, $ids, 6 * HOUR_IN_SECONDS );

    return $ids;
}


/* =========================================================================
 *  LISTINGS
 * ========================================================================= */

// 📥 Fetch Listings with Pagination and Filters (CACHED ~5 min)
function pf_get_properties($page = 1, $perPage = 30, $filters = []) {

    // Build URL with base parameters
    $url = 'https://atlas.propertyfinder.com/v1/listings?perPage=' . $perPage . '&page=' . $page;
    
    // Add filter parameters to API request (using correct API parameter names)
    
    // Offering Type: rent or sale
    if (!empty($filters['offering_type'])) {
        $url .= '&filter[offeringType]=' . urlencode($filters['offering_type']);
    }
    
    // Property Type: apartment, villa, etc
    if (!empty($filters['property_type'])) {
        $url .= '&filter[type]=' . urlencode($filters['property_type']);
    }
    
    // ✅ Location filtering
    if (!empty($filters['location_id'])) {
        // exact leaf match
        $url .= '&filter[locationId]=' . urlencode($filters['location_id']);
    } elseif (!empty($filters['location_tree_filter'])) {
        // expand community/project/etc to its descendants
        $desc = pf_get_descendant_location_ids($filters['location_tree_filter']);
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('PF descendants for parent ' . $filters['location_tree_filter'] . ': ' . json_encode($desc));
        }
        if (!empty($desc)) {
            $url .= '&filter[locationId]=' . urlencode(implode(',', $desc));
        } else {
            // fall back to the parent id (may return none, but keeps behavior predictable)
            $url .= '&filter[locationId]=' . urlencode($filters['location_tree_filter']);
        }
    }
    
    // Bedrooms
    if (!empty($filters['bedrooms'])) {
        $url .= '&filter[bedrooms]=' . urlencode($filters['bedrooms']);
    }
    
    // Bathrooms
    if (!empty($filters['bathrooms'])) {
        $url .= '&filter[bathrooms]=' . urlencode($filters['bathrooms']);
    }
    
    // Price Range
    if (!empty($filters['min_price'])) {
        $url .= '&filter[price][from]=' . floatval($filters['min_price']);
    }
    if (!empty($filters['max_price'])) {
        $url .= '&filter[price][to]=' . floatval($filters['max_price']);
    }

    // ⚡ Short cache so repeat visits / pagination don't re-hit the API.
    $cache_key = 'pf_listings_' . md5( $url );
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $data = pf_authenticated_get( $url, [], 30 );
    if ( $data === null ) return [];

    set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

    return $data;
}

// 🏡 Display Properties with Pagination and Location Tree Filtering (OPTIMIZED)
function pf_display_listings($atts) {

    $atts = shortcode_atts(['per_page' => 30], $atts, 'pf_listings');
    $page = isset($_GET['pf_page']) ? max(1, intval($_GET['pf_page'])) : 1;
    
    // 🧭 Get filters from URL
    $filter_type = isset($_GET['price-type']) ? strtolower(trim($_GET['price-type'])) : '';
    $filter_property_type = isset($_GET['property-type']) ? strtolower(trim($_GET['property-type'])) : '';
    $filter_bedrooms = isset($_GET['bedrooms']) ? trim($_GET['bedrooms']) : '';
    $filter_bathrooms = isset($_GET['bathrooms']) ? trim($_GET['bathrooms']) : '';
    $filter_min_price = isset($_GET['min-price']) ? floatval($_GET['min-price']) : '';
    $filter_max_price = isset($_GET['max-price']) ? floatval($_GET['max-price']) : '';
    $filter_location = isset($_GET['location']) ? trim($_GET['location']) : '';
    $filter_location_id = isset($_GET['location-id']) ? trim($_GET['location-id']) : '';

    // Map URL value to API's offeringType parameter
    $type_map = [
        'buy'  => 'sale',
        'rent' => 'rent',
    ];
    $offering_type_filter = $type_map[$filter_type] ?? '';
    
    // Build filters array for API
    $filters = [];
    
    // Offering type (rent/sale)
    if (!empty($offering_type_filter)) {
        $filters['offering_type'] = $offering_type_filter;
    }
    
    // Property type (apartment, villa, etc)
    if (!empty($filter_property_type)) {
        $filters['property_type'] = $filter_property_type;
    }
    
    // 🌳 Location Tree Filtering Logic
    if (!empty($filter_location_id)) {
        // Get full location data
        $location_full_data = pf_get_location_full_data($filter_location_id);
        
        if ($location_full_data && is_array($location_full_data)) {
            $location_type = $location_full_data['type'] ?? '';
            
            // Parent location types that should include children
            $parent_types = ['CITY', 'COMMUNITY', 'SUBCOMMUNITY', 'PROJECT', 'COMPOUND', 'AREA', 'DISTRICT'];
            
            if (in_array($location_type, $parent_types)) {
                $filters['location_tree_filter'] = $filter_location_id; 
            } else {
                $filters['location_id'] = $filter_location_id;
            }
        } else {
            $filters['location_id'] = $filter_location_id;
        }
    }
    
    // Location/Community search
    if (!empty($filter_location)) {
        $filters['search_query'] = $filter_location;
    }
    
    // Bedrooms
    if (!empty($filter_bedrooms)) {
        $filters['bedrooms'] = $filter_bedrooms;
    }
    
    // Bathrooms
    if (!empty($filter_bathrooms)) {
        $filters['bathrooms'] = $filter_bathrooms;
    }
    
    // Price range
    if (!empty($filter_min_price)) {
        $filters['min_price'] = $filter_min_price;
    }
    if (!empty($filter_max_price)) {
        $filters['max_price'] = $filter_max_price;
    }

    // Fetch properties with filters
    $data = pf_get_properties($page, $atts['per_page'], $filters);
    $properties = $data['results'] ?? [];
    $pagination = $data['pagination'] ?? [];

    if (empty($properties)) return '<p>No properties found.</p>';

    // ⚡ OPTIMIZATION: Batch fetch all location details at once
    $location_ids = array_unique(array_filter(array_map(fn($p) => $p['location']['id'] ?? 0, $properties)));
    $locations = pf_batch_get_location_details($location_ids);

    $output = '<div class="pf-listings-wrapper">';
    $output .= '<div class="pf-listings" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">';

    foreach ($properties as $property) {
        $propertyType = strtolower($property['type'] ?? '');
        $rawBedrooms = $property['bedrooms'] ?? null;
        $bedrooms = pf_normalize_bedrooms($rawBedrooms);
        $bathrooms = intval($property['bathrooms'] ?? 0);
        
        $title = $property['title']['en'] ?? 'No Title';
        $offPriceType = $property['price']['type'] ?? '';
        $priceType = $offPriceType;
        if (strtolower($priceType) === 'yearly') $priceType = 'Rent';
        
        $size = $property['size']['value'] ?? ($property['size'] ?? '');
        $location_id = $property['location']['id'] ?? 0;
        $location = $locations[$location_id] ?? '';

       $images = $property['media']['images'] ?? [];
$firstImage = $images[0]['watermarked']['url']
    ?? $images[0]['large']['url']
    ?? $images[0]['original']['url']
    ?? $images[0]['medium']['url']
    ?? 'https://via.placeholder.com/400x300?text=No+Image';


        $output .= '<div class="pf-property" style="border:1px solid #ddd; overflow:hidden;">';
        $output .= '<img src="' . esc_url($firstImage) . '" alt="' . esc_attr($title) . '" loading="lazy" decoding="async" style="width:100%; height:250px; object-fit:cover;"/>';
        $output .= '<div style="padding:15px;">';
        $output .= '<h3 style="margin:0 0 10px;">' . esc_html($title) . '</h3>';
        $output .= '<p class="prop-typ">' . esc_html(ucfirst($propertyType)) . ' <span class="prop-pric-typ">' . esc_html($priceType) . '</span></p>';
		
		$priceAmount = $property['price']['amounts'][$offPriceType] ?? 0;
        if (!empty($priceAmount)) {
            $formattedPrice = number_format($priceAmount, 2, '.', ',');
            $output .= '<p class="price-txt">' . esc_html($formattedPrice) . ' AED';
            if (strtolower($offPriceType) === 'yearly') $output .= '<sub>/Yearly</sub>';
            $output .= '</p>';
        }
		
        if ($location) $output .= '<p class="prop-location"><svg aria-hidden="true" class="e-font-icon-svg e-fas-map-marker-alt" viewBox="0 0 384 512" xmlns="http://www.w3.org/2000/svg"><path d="M172.268 501.67C26.97 291.031 0 269.413 0 192 0 85.961 85.961 0 192 0s192 85.961 192 192c0 77.413-26.97 99.031-172.268 309.67-9.535 13.774-29.93 13.773-39.464 0zM192 272c44.183 0 80-35.817 80-80s-35.817-80-80-80-80 35.817-80 80 35.817 80 80 80z"></path></svg>' . esc_html($location) . '</p>';
		
 $output .= '<div class="pf-property-bottom-section">';
        $output .= '<div class="grid-prop-cont">';
        if ($bedrooms !== null) {
            $output .= '<div class="inner-cont-a"><img src="https://desea.ae/wp-content/uploads/2025/10/bed.svg" loading="lazy" decoding="async" style="height: 25px; margin-right: 10px;"><p>'
                    . esc_html($bedrooms === 'studio' ? 'Studio' : $bedrooms)
                    . '</p></div>';
        }

        if ($bathrooms) $output .= '<div class="inner-cont-a"><img src="https://desea.ae/wp-content/uploads/2025/10/bath-tub.svg" loading="lazy" decoding="async" style="height: 25px; margin-right: 10px;"><p>' . esc_html($bathrooms) . '</p></div>';
        if ($size) $output .= '<div class="inner-cont-a"><img src="https://desea.ae/wp-content/uploads/2025/10/square.svg" loading="lazy" decoding="async" style="height: 25px; margin-right: 10px;"><p>' . esc_html($size) . ' sqft</p></div>';
        $output .= '</div>';

        
        $reference = $property['reference'] ?? '';
        if ($reference) {
            $output .= '<a href="' . site_url('/property-details/?ref=' . urlencode($reference)) . '" 
                style="display:inline-block; padding:8px 12px; background:#0073aa; color:#fff; border-radius:4px; text-decoration:none;">
                View Details
            </a>';
        }

        $output .= '</div></div></div>';
    }

    $output .= '</div>';
    
    // Pagination
    if (!empty($pagination)) {
        $totalPages = $pagination['totalPages'] ?? 1;
        if ($totalPages > 1) {
            $output .= '<div class="pf-pagination" style="margin-top:20px; text-align:center;">';
            $query_params = $_GET;

            if ($page > 1) {
                $query_params['pf_page'] = $page - 1;
                $prev_url = '?' . http_build_query($query_params);
                $output .= '<a href="' . esc_url($prev_url) . '" style="margin:0 10px;">&laquo; PREVIOUS</a>';
            }

            $range = 2;
            $ellipsisAdded = false;
            for ($i = 1; $i <= $totalPages; $i++) {
                if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                    $query_params['pf_page'] = $i;
                    $page_url = '?' . http_build_query($query_params);

                    if ($i == $page) {
                        $output .= '<span style="margin:0 5px; font-weight:bold;">' . $i . '</span>';
                    } else {
                        $output .= '<a href="' . esc_url($page_url) . '" style="margin:0 5px;">' . $i . '</a>';
                    }
                    $ellipsisAdded = false;
                } elseif (!$ellipsisAdded) {
                    $output .= '<span style="margin:0 5px;">...</span>';
                    $ellipsisAdded = true;
                }
            }

            if ($page < $totalPages) {
                $query_params['pf_page'] = $page + 1;
                $next_url = '?' . http_build_query($query_params);
                $output .= '<a href="' . esc_url($next_url) . '" style="margin:0 10px;">NEXT &raquo;</a>';
            }

            $output .= '</div>';
        }
    }

    $output .= '</div>';
    return $output;
}
add_shortcode('pf_listings', 'pf_display_listings');

// ⚡ Batch fetch location details (much faster than individual calls) — CACHED
function pf_batch_get_location_details($location_ids) {
    if (empty($location_ids)) return [];
    
    // Use caching to avoid repeated API calls
    $cache_key = 'pf_locations_batch_' . md5(implode(',', $location_ids));
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    $locations = [];
    
    // Fetch in batches of 50 (API limit)
    $batches = array_chunk($location_ids, 50);
    
    foreach ($batches as $batch) {
        $ids_param = implode(',', $batch);
        $url = 'https://atlas.propertyfinder.com/v1/locations?filter[id]=' . $ids_param . '&perPage=50';
        
        $data = pf_authenticated_get( $url, [ 'Accept-Language' => 'en' ], 20 );
        if ( $data === null ) continue;
        
        if (!empty($data['data'])) {
            foreach ($data['data'] as $location_data) {
                $id = $location_data['id'] ?? 0;
                if (!$id) continue;
                
                $name = $location_data['name'] ?? '';
                
                // Build full location path
                if (!empty($location_data['tree'])) {
                    $tree_names = array_map(function($t) { 
                        return $t['name'] ?? ''; 
                    }, $location_data['tree']);
                    $tree_names = array_reverse($tree_names);
                    $name = implode(', ', $tree_names);
                }
                
                $locations[$id] = $name;
            }
        }
    }
    
    // Cache for 1 hour
    set_transient($cache_key, $locations, HOUR_IN_SECONDS);
    
    return $locations;
}

// Normalize bedrooms helper function
function pf_normalize_bedrooms($val) {
    if ($val === null) return null;
    if (is_string($val)) {
        $v = strtolower(trim($val));
        if ($v === 'studio' || $v === 'st') return 'studio';
        if (is_numeric($v)) return (int) $v;
        return null;
    }
    if (is_numeric($val)) return (int) $val;
    return null;
}



// Prefer watermarked > large > original > medium > thumbnail
function pf_best_image_url(array $img, string $fallback = ''): string {
    $candidates = [
        $img['watermarked']['url'] ?? null,
        $img['large']['url']        ?? null,
        $img['original']['url']     ?? null,
        $img['medium']['url']       ?? null,
        $img['thumbnail']['url']    ?? null,
    ];
    foreach ($candidates as $u) {
        if (!empty($u)) return $u;
    }
    return $fallback;
}



// 🔹 Single Property Page with Gallery and Two-Column Detail



function pf_display_property_header_shortcode($atts) {
	
	
    // Check for the 'ref' in URL first
    $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';

    if (empty($ref)) {
        return '<p>Property reference not found.</p>';
    }

    if (!pf_get_auth_token()) {
        return '<p>Failed to authenticate with the API.</p>';
    }

    // ⚡ Shared single fetch (header + details + <title> reuse this)
    $property = pf_get_listing_by_ref($ref);

    global $pf_property_for_title;
    $pf_property_for_title = $property;
	

    if (!$property) {
        return '<p>No property found with this reference.</p>';
    }

    // --- Data Extraction ---
    $title = $property['title']['en'] ?? 'No Title';
	

    $location_id = $property['location']['id'] ?? 0;
    $location = $location_id ? pf_get_location_details($location_id) : '';
    $priceType = $property['price']['type'] ?? '';
	$offPriceType = $property['price']['type'] ?? '';
if (strtolower($priceType) === 'yearly') {
    $priceType = 'Rent';
}
    $priceAmount = $property['price']['amounts'][$offPriceType] ?? '';
    
	
	$images     = $property['media']['images'] ?? [];
$imageCount = count($images);

$mainImage = isset($images[0])
    ? pf_best_image_url($images[0], 'https://via.placeholder.com/1000x700?text=No+Image')
    : 'https://via.placeholder.com/1000x700?text=No+Image';

$secondaryImage1 = isset($images[1])
    ? pf_best_image_url($images[1], 'https://via.placeholder.com/500x350?text=No+Image')
    : 'https://via.placeholder.com/500x350?text=No+Image';

$secondaryImage2 = isset($images[2])
    ? pf_best_image_url($images[2], 'https://via.placeholder.com/500x350?text=No+Image')
    : 'https://via.placeholder.com/500x350?text=No+Image';

// Build the array for the lightbox using watermarked-first logic
$jsImages = array_map(function($img) {
    return pf_best_image_url($img, 'https://via.placeholder.com/1000x700?text=No+Image');
}, $images);

	
	
    $formattedPrice = number_format($priceAmount, 2, '.', ',');

    // --- HTML Output ---
    $output = '<div class="pf-property-header-wrapper" style="max-width:1200px; margin:0 auto; font-family:Arial, sans-serif;"> <div class="int-cont-tit">';
    $output .= '<h1 style="margin-bottom:5px;">' . esc_html($title) . '</h1>';
	$output .= '<span class="price-typ">' . esc_html($priceType) . '</span> </div>';
	 
    // Image Gallery Layout
    $output .= '<div class="pf-image-gallery" style="display:flex; gap:10px; position:relative; overflow:hidden;">';
    
    // Main image (70%) — above-the-fold LCP: eager + high priority
    $output .= '<div style="flex:7; position:relative; overflow:hidden;">';
    $output .= '<img src="' . esc_url($mainImage) . '" alt="' . esc_attr($title) . '" fetchpriority="high" decoding="async" style="width:100%; height:auto; display:block; object-fit:cover; border-radius:0px;">';
    $output .= '</div>';

    // Secondary images (30%)
    $output .= '<div style="flex:3; display:flex; flex-direction:column; gap:10px;">';
    $output .= '<img src="' . esc_url($secondaryImage1) . '" alt="Property secondary image 1" decoding="async" style="width:100%; height:50%; object-fit:cover;">';
    $output .= '<div style="position:relative; width:100%; height:50%;">';
    $output .= '<img src="' . esc_url($secondaryImage2) . '" alt="Property secondary image 2" decoding="async" style="width:100%; height:100%; object-fit:cover; ">';
    $output .= '<span id="more-images-text" style="position:absolute; bottom:10px; right:10px; background:rgba(0,0,0,0.5); color:#fff; padding:5px 10px; border-radius:5px; font-size:14px; cursor:pointer;">+' . max(0, $imageCount - 3) . ' images</span>';
    $output .= '</div>';
    $output .= '</div>';

    $output .= '</div>'; // End pf-image-gallery

    // Location and Price (after gallery)
    $output .= '<div class="single-page-pric-loc" style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">';
    $output .= '<p class="sing-locat"> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 3.001C10.1435 3.001 8.36301 3.7385 7.05025 5.05125C5.7375 6.36401 5 8.14448 5 10.001C5 12.863 6.782 15.624 8.738 17.763C9.73814 18.8526 10.8294 19.8549 12 20.759C12.1747 20.625 12.3797 20.4617 12.615 20.269C13.5548 19.4973 14.4393 18.6605 15.262 17.765C17.218 15.624 19 12.864 19 10.001C19 8.14448 18.2625 6.36401 16.9497 5.05125C15.637 3.7385 13.8565 3.001 12 3.001ZM12 23.215L11.433 22.825L11.43 22.823L11.424 22.818L11.404 22.804L11.329 22.751L11.059 22.554C9.69086 21.5258 8.41988 20.3743 7.262 19.114C5.218 16.876 3 13.637 3 10C3 7.61305 3.94821 5.32387 5.63604 3.63604C7.32387 1.94821 9.61305 1 12 1C14.3869 1 16.6761 1.94821 18.364 3.63604C20.0518 5.32387 21 7.61305 21 10C21 13.637 18.782 16.877 16.738 19.112C15.5804 20.3723 14.3098 21.5237 12.942 22.552C12.8281 22.6371 12.713 22.7208 12.597 22.803L12.576 22.817L12.57 22.822L12.568 22.823L12 23.215ZM12 8.001C11.4696 8.001 10.9609 8.21171 10.5858 8.58679C10.2107 8.96186 10 9.47057 10 10.001C10 10.5314 10.2107 11.0401 10.5858 11.4152C10.9609 11.7903 11.4696 12.001 12 12.001C12.5304 12.001 13.0391 11.7903 13.4142 11.4152C13.7893 11.0401 14 10.5314 14 10.001C14 9.47057 13.7893 8.96186 13.4142 8.58679C13.0391 8.21171 12.5304 8.001 12 8.001ZM8 10.001C8 8.94013 8.42143 7.92272 9.17157 7.17257C9.92172 6.42243 10.9391 6.001 12 6.001C13.0609 6.001 14.0783 6.42243 14.8284 7.17257C15.5786 7.92272 16 8.94013 16 10.001C16 11.0619 15.5786 12.0793 14.8284 12.8294C14.0783 13.5796 13.0609 14.001 12 14.001C10.9391 14.001 9.92172 13.5796 9.17157 12.8294C8.42143 12.0793 8 11.0619 8 10.001Z" fill="#444444"></path></svg>' . esc_html($location) . '</p>';
    $output .= '<p class="sing-price">AED ' . esc_html($formattedPrice) . '';
	// Add the /Yearly label only if the price type is 'yearly'
    if (strtolower($offPriceType) === 'yearly') {
        $output .= '<sub>/Yearly</sub>';
    }
    
    $output .= '</p>';
    
    $output .= '</div>';


    // Lightbox Container (Hidden by default)
    $output .= '<div id="pf-lightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2147483647; justify-content:center; align-items:center;">';
    $output .= '<button id="pf-lightbox-close" style="position:absolute; top:20px; right:30px; background:none; border:none; color:#fff; font-size:40px; cursor:pointer;">&times;</button>';
    $output .= '<button id="pf-lightbox-prev" style="position:absolute; left:20px; top:50%; transform:translateY(-50%); background:none; border:none; color:#fff; font-size:50px; cursor:pointer;">&larr;</button>';
    $output .= '<button id="pf-lightbox-next" style="position:absolute; right:20px; top:50%; transform:translateY(-50%); background:none; border:none; color:#fff; font-size:50px; cursor:pointer;">&rarr;</button>';
    $output .= '<img id="pf-lightbox-img" src="" style="max-width:80%; max-height:80%; object-fit:contain;">';
    $output .= '<div id="pf-lightbox-thumbnails" style="position:absolute; bottom:20px; display:flex; gap:5px; max-width:80%; overflow-x:auto;"></div>';
    $output .= '</div>'; // End pf-lightbox

    // JavaScript for gallery and lightbox
    ob_start();
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const lightbox = document.getElementById('pf-lightbox');
            const lightboxImg = document.getElementById('pf-lightbox-img');
            const lightboxClose = document.getElementById('pf-lightbox-close');
            const lightboxPrev = document.getElementById('pf-lightbox-prev');
            const lightboxNext = document.getElementById('pf-lightbox-next');
            const thumbContainer = document.getElementById('pf-lightbox-thumbnails');
            const body = document.body;
            
            const images = <?php echo json_encode($jsImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            let currentIndex = 0;

            function updateLightboxImage() {
                lightboxImg.src = images[currentIndex];
                // Update thumbnail highlight
                const thumbs = thumbContainer.querySelectorAll('img');
                thumbs.forEach((thumb, index) => {
                    if (index === currentIndex) {
                        thumb.style.border = '2px solid #fff';
                        thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    } else {
                        thumb.style.border = '1px solid #777';
                    }
                });
            }

            function showLightbox(index) {
                currentIndex = index;
                updateLightboxImage();
                lightbox.style.display = 'flex';
                body.style.overflow = 'hidden';
                body.style.position = 'fixed'; // Prevents scrolling on some mobile devices
                body.style.width = '100%';
            }

            function nextImage() {
                currentIndex = (currentIndex + 1) % images.length;
                updateLightboxImage();
            }

            function prevImage() {
                currentIndex = (currentIndex - 1 + images.length) % images.length;
                updateLightboxImage();
            }
            
            // Create thumbnails in the lightbox
            images.forEach((url, index) => {
                const thumb = document.createElement('img');
                thumb.src = url;
                thumb.loading = 'lazy';
                thumb.style.width = '60px';
                thumb.style.height = '60px';
                thumb.style.objectFit = 'cover';
                thumb.style.cursor = 'pointer';
                thumb.style.borderRadius = '4px';
                thumb.addEventListener('click', () => showLightbox(index));
                thumbContainer.appendChild(thumb);
            });

            // Event listeners
            document.querySelector('.pf-image-gallery').addEventListener('click', () => showLightbox(0));
            lightboxClose.addEventListener('click', () => {
                lightbox.style.display = 'none';
                body.style.overflow = '';
                body.style.position = '';
                body.style.width = '';
            });
            lightboxPrev.addEventListener('click', prevImage);
            lightboxNext.addEventListener('click', nextImage);
        });
    </script>
    <?php
    $output .= ob_get_clean();
    $output .= '</div>'; // close wrapper

    return $output;
}
add_shortcode('pf_property_header', 'pf_display_property_header_shortcode');



/**
 * Shortcode to display specific property details.
 * Usage: [pf_property_details]
 */
function pf_display_property_details_shortcode() {
    // Check for 'ref' in the URL
    $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';

    if (empty($ref)) {
        return '<p>Property reference not found.</p>';
    }

    $token = pf_get_auth_token();
    if (!$token) {
        return '<p>Failed to authenticate with the API.</p>';
    }

    // ⚡ Shared single fetch (reuses header's request for the same ref)
    $property = pf_get_listing_by_ref($ref);

    if (!$property) {
        return '<p>No property found with this reference.</p>';
    }

    $output = '<div class="pf-property-details-shortcode" style="max-width:1000px; margin:0 auto; font-family:Arial, sans-serif;">';
	
	// --- Description Section ---
if (!empty($property['description']['en'])) {
    $description_with_breaks = nl2br(esc_html($property['description']['en']));

    $output .= '
    <div style="margin-top:20px;">
        <h3>Description</h3>
        <div class="desc-wrapper">
            <div class="desc-content">' . wp_kses_post($description_with_breaks) . '</div>
            <div class="desc-fade"></div>
        </div>
        <button class="desc-toggle">Show More</button>
    </div>



    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const wrapper = document.querySelector(".desc-wrapper");
            const button = document.querySelector(".desc-toggle");
            if (wrapper && button) {
                button.addEventListener("click", function() {
                    wrapper.classList.toggle("expanded");
                    button.textContent = wrapper.classList.contains("expanded") ? "Show Less" : "Show More";
                });
            }
        });
    </script>
    ';
}


    // Property Size Calculation
    $size = $property['size']['value'] ?? ($property['size'] ?? 0);
    $sizeSqft = is_numeric($size) ? $size : 0;
    $sizeSqm = $sizeSqft * 0.092903;

    // --- Property Details Section ---
    $details = [
        'Property Type'          => ucfirst($property['type'] ?? ''),
        'Property Size'          => ($sizeSqft > 0 ? $sizeSqft . ' sqft / ' . round($sizeSqm, 2) . ' sqm' : ''),
        'Bedrooms'               => $property['bedrooms'] ?? '',
        'Bathrooms'              => $property['bathrooms'] ?? '',
        'Available From'         => !empty($property['availableFrom']) ? date('d M Y', strtotime($property['availableFrom'])) : '',
        'Finishing / Furnishing' => trim(($property['finishingType'] ?? '') . ' / ' . ($property['furnishingType'] ?? '')),
        'Unit Number'            => $property['unitNumber'] ?? '',
        'Category'               => ucfirst($property['category'] ?? ''),
        'Parking Slots'          => $property['parkingSlots'] ?? '',
    ];

    $output .= '<h3>Property Details</h3>';
    $output .= '<div class="property-details-cont" style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:10px;">';
    foreach ($details as $label => $value) {
        if (!empty($value) && $value !== 'N/A') {
            $output .= '<div><strong>' . esc_html($label) . '</strong><br>' . esc_html($value) . '</div>';
        }
    }
    $output .= '</div>';

    

    // --- Amenities Section ---
if (!empty($property['amenities'])) {
    $output .= '
    <div style="margin-top:20px;">
        <h3>Amenities</h3>
        <ul class="amenities-list">';
            foreach ($property['amenities'] as $amenity) {
                $label = ucwords(str_replace(['-', '_'], ' ', $amenity));
                if ($label) {
                    $output .= '<li>' . esc_html($label) . '</li>';
                }
            }
    $output .= '
        </ul>
    </div>


    ';
}


    // --- Permit Information Section ---
    $permitNumber = $property['compliance']['listingAdvertisementNumber'] ?? '';
    $licenseNumber = $property['compliance']['issuingClientLicenseNumber'] ?? 'YOUR_LICENSE_NUMBER';
    $permitType    = $property['compliance']['type'] ?? 'listing';

    if (!empty($permitNumber)) {
        $compliance_url = "https://atlas.propertyfinder.com/v1/compliances/{$permitNumber}/{$licenseNumber}?permitType={$permitType}";

        // ⚡ Cache compliance lookups for 6 hours (permit data is stable)
        $compliance_cache_key = 'pf_compliance_' . md5($compliance_url);
        $permit = get_transient($compliance_cache_key);

        if ($permit === false) {
            $compliance_data = pf_authenticated_get($compliance_url, [ 'Accept-Language' => 'en' ], 30);
            $permit = $compliance_data['data'][0] ?? [];
            set_transient($compliance_cache_key, $permit, 6 * HOUR_IN_SECONDS);
        }

        $output .= '<div class="permit-info-cont" style="margin-top:20px;">';
        $output .= '<h3>Permit Information</h3>';
        $output .= '<p><strong>Permit Number:</strong> ' . esc_html($permit['permitNumber'] ?? $permitNumber) . '</p>';

        if (!empty($permit['expiresAt'])) {
            $output .= '<p><strong>Expires At:</strong> ' . date('d M Y', strtotime($permit['expiresAt'])) . '</p>';
        }

        if (!empty($permit['validationURL'])) {
            $output .= '<p><a href="' . esc_url($permit['validationURL']) . '" target="_blank" rel="noopener">Validate Permit</a></p>';
        }
        $output .= '</div>';
    }
    
    $output .= '</div>'; // close main wrapper

    return $output;
}
add_shortcode('pf_property_details', 'pf_display_property_details_shortcode');



/**
 * Changes the document title to the property title fetched from the API.
 *
 * @param string $title The original document title.
 * @return string The new document title.
 */
function pf_dynamic_property_page_title($title) {
    // Only proceed on the front-end and on the specific property page
    if (is_admin() || !is_page('property-details')) {
        return $title;
    }

    $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';

    // If no reference is provided, return the original title
    if (empty($ref)) {
        return $title;
    }

    // Get the API authentication token
    if (!pf_get_auth_token()) {
        return $title; // Return original title if authentication fails
    }

    // ⚡ Shared single fetch (reuses the header/details request for the same ref)
    $property = pf_get_listing_by_ref($ref);

    if ($property && isset($property['title']['en'])) {
        // Use the property title from the API response
        $property_title = $property['title']['en'];
        return $property_title;
    }

    // If property data is not found or has no title, return a fallback or the original title
    return $title;
}
add_filter('pre_get_document_title', 'pf_dynamic_property_page_title');


// 🎯 AJAX Handler - Search Locations (Real-time API search) — CACHED per term
add_action('wp_ajax_pf_search_locations', 'pf_ajax_search_locations');
add_action('wp_ajax_nopriv_pf_search_locations', 'pf_ajax_search_locations');

function pf_ajax_search_locations() {
    $search_term = isset($_GET['term']) ? trim($_GET['term']) : '';
    
    if (strlen($search_term) < 2) {
        wp_send_json([]);
        return;
    }

    // ⚡ Cache identical search terms for 1 hour
    $cache_key = 'pf_loc_search_' . md5(strtolower($search_term));
    $cached    = get_transient($cache_key);
    if ($cached !== false) {
        wp_send_json($cached);
        return;
    }
    
    if (!pf_get_auth_token()) {
        wp_send_json([]);
        return;
    }
    
    // Search locations via API
    $url = 'https://atlas.propertyfinder.com/v1/locations?search=' . urlencode($search_term) . '&perPage=15';
    
    $data = pf_authenticated_get( $url, [ 'Accept-Language' => 'en' ], 10 );
    if ( $data === null ) {
        wp_send_json([]);
        return;
    }
    
    $results = [];
    
    // Check multiple possible response structures
    $locations_data = [];
    if (!empty($data['data'])) {
        $locations_data = $data['data'];
    } elseif (!empty($data['results'])) {
        $locations_data = $data['results'];
    }
    
    if (!empty($locations_data)) {
        foreach ($locations_data as $location) {
            $location_id = $location['id'] ?? 0;
            $name = $location['name'] ?? '';
            
            // Build full location path (e.g., "Dubai Marina, Dubai, UAE")
            $full_name = $name;
            if (!empty($location['tree'])) {
                $tree_names = array_map(function($t) {
                    return $t['name'] ?? '';
                }, $location['tree']);
                $tree_names = array_reverse($tree_names);
                $full_name = implode(', ', $tree_names);
            }
            
            if ($location_id && $full_name) {
                $results[] = [
                    'id' => $location_id,
                    'label' => $full_name,
                    'value' => $full_name
                ];
            }
        }
    }

    set_transient($cache_key, $results, HOUR_IN_SECONDS);
    
    wp_send_json($results);
}

// 🔍 AJAX Handler to load locations asynchronously (OPTIMIZED: batch, no N+1)
add_action('wp_ajax_pf_load_locations', 'pf_ajax_load_locations');
add_action('wp_ajax_nopriv_pf_load_locations', 'pf_ajax_load_locations');

function pf_ajax_load_locations() {
    // Check cache first (1 hour cache)
    $cache_key = 'pf_locations_list';
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        wp_send_json_success($cached);
        return;
    }
    
    // Fetch locations from properties
    $all_properties = [];
    
    for ($page = 1; $page <= 10; $page++) {
        $data = pf_get_properties($page, 100, []);
        $results = $data['results'] ?? [];
        
        if (empty($results)) break;
        $all_properties = array_merge($all_properties, $results);
    }
    
    if (empty($all_properties)) {
        wp_send_json_error('No locations available');
        return;
    }
    
    // Extract unique location IDs
    $location_ids = array_values(array_unique(array_filter(
        array_map(fn($p) => $p['location']['id'] ?? 0, $all_properties)
    )));

    // ⚡ Batch fetch all names in one set of calls (was 1 auth+request PER id)
    $names_map = pf_batch_get_location_details($location_ids);

    $locations_data = [];
    foreach ($names_map as $id => $location_name) {
        if ($id && $location_name) {
            $locations_data[] = [
                'id'   => $id,
                'name' => $location_name
            ];
        }
    }
    
    // Sort locations alphabetically
    usort($locations_data, fn($a, $b) => strcmp($a['name'], $b['name']));
    
    // Cache for 1 hour
    set_transient($cache_key, $locations_data, HOUR_IN_SECONDS);
    
    wp_send_json_success($locations_data);
}

// 🔍 Enhanced Location Search Shortcode with Filters
function pf_location_search_shortcode($atts) {
    $atts = shortcode_atts([
        'placeholder' => 'Search location...',
        'use_api' => 'false'
    ], $atts, 'pf_location_search');

    $instance_id = 'pf-loc-search-' . uniqid();
    $use_api = filter_var($atts['use_api'], FILTER_VALIDATE_BOOLEAN);
    
    // Price options
    $price_options = [
        300000, 400000, 500000, 600000, 700000, 800000, 900000, 
        1000000, 1100000, 1200000, 1300000, 1400000, 1500000, 1600000, 
        1700000, 1800000, 1900000, 2000000, 2100000, 2200000, 2300000, 
        2400000, 2500000, 2600000, 2700000, 2800000, 2900000, 3000000, 
        3250000, 3500000, 3750000, 4000000, 4250000, 4500000, 4750000, 
        5000000, 6000000, 7000000, 8000000, 9000000, 10000000, 25000000, 50000000
    ];

    ob_start(); ?>

    <style>
        .pf-search-container {
            max-width: 100%;
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .pf-search-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .pf-search-row + .pf-search-row {
            margin-top: 15px;
        }
        .pf-search-field {
            position: relative;
            flex: 1;
            min-width: 150px;
        }
        .pf-search-field-full {
            position: relative;
            flex: 1 1 100%;
        }
        .pf-search-field label,
        .pf-search-field-full label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            font-weight: 600;
            color: #333;
        }
        .pf-search-field input,
        .pf-search-field select,
        .pf-search-field-full input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: #fff;
        }
        .pf-search-field select {
            cursor: pointer;
        }
        .pf-search-btn {
            padding: 10px 25px;
            background: #0073aa;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
            height: 42px;
            white-space: nowrap;
        }
        .pf-search-btn:hover {
            background: #005a87;
        }
        .pf-suggestions-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .pf-suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        .pf-suggestion-item:hover {
            background: #f0f0f0;
        }
        @media (max-width: 768px) {
            .pf-search-field {
                flex: 1 1 calc(50% - 10px);
            }
        }
    </style>

    <div class="pf-search-container">
        <!-- Row 1: Location (Full Width) -->
        <div class="pf-search-row">
            <div class="pf-search-field-full">
                <label for="<?php echo $instance_id; ?>-input">Location</label>
                <input type="text"
                    id="<?php echo $instance_id; ?>-input"
                    placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                    autocomplete="off" />
                <input type="hidden" id="<?php echo $instance_id; ?>-location-id" value="" />
                <div id="<?php echo $instance_id; ?>-suggestions" class="pf-suggestions-dropdown"></div>
            </div>
        </div>

        <!-- Row 2: All Other Filters -->
        <div class="pf-search-row">
            <div class="pf-search-field">
                <label for="<?php echo $instance_id; ?>-price-type">Price Type</label>
                <select id="<?php echo $instance_id; ?>-price-type">
                    <option value="">Any</option>
                    <option value="buy">Sale</option>
                    <option value="rent">Rent</option>
                </select>
            </div>

            <div class="pf-search-field">
                <label for="<?php echo $instance_id; ?>-property-type">Property Type</label>
                <select id="<?php echo $instance_id; ?>-property-type">
                    <option value="">Any Type</option>
                    <option value="apartment">Apartment</option>
                    <option value="land">Land</option>
                    <option value="office-space">Office Space</option>
                    <option value="penthouse">Penthouse</option>
                    <option value="townhouse">Townhouse</option>
                    <option value="villa">Villa</option>
                </select>
            </div>

            <div class="pf-search-field">
                <label for="<?php echo $instance_id; ?>-bedrooms">Bedrooms</label>
                <select id="<?php echo $instance_id; ?>-bedrooms">
                    <option value="">Any</option>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="pf-search-field">
                <label for="<?php echo $instance_id; ?>-bathrooms">Bathrooms</label>
                <select id="<?php echo $instance_id; ?>-bathrooms">
                    <option value="">Any</option>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="pf-search-field">
                <label for="<?php echo $instance_id; ?>-min-price">Min Price</label>
                <select id="<?php echo $instance_id; ?>-min-price">
                    <option value="">No Min</option>
                    <?php foreach ($price_options as $price): ?>
                        <option value="<?php echo $price; ?>"><?php echo number_format($price); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pf-search-field">
                <label for="<?php echo $instance_id; ?>-max-price">Max Price</label>
                <select id="<?php echo $instance_id; ?>-max-price">
                    <option value="">No Max</option>
                    <?php foreach ($price_options as $price): ?>
                        <option value="<?php echo $price; ?>"><?php echo number_format($price); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button id="<?php echo $instance_id; ?>-btn" class="pf-search-btn">
                Search
            </button>
        </div>
    </div>

    <script>
        (function() {
            const inputEl = document.getElementById('<?php echo $instance_id; ?>-input');
            const hiddenEl = document.getElementById('<?php echo $instance_id; ?>-location-id');
            const suggestionsEl = document.getElementById('<?php echo $instance_id; ?>-suggestions');
            const btnEl = document.getElementById('<?php echo $instance_id; ?>-btn');
            const priceTypeEl = document.getElementById('<?php echo $instance_id; ?>-price-type');
            const propertyTypeEl = document.getElementById('<?php echo $instance_id; ?>-property-type');
            const bedroomsEl = document.getElementById('<?php echo $instance_id; ?>-bedrooms');
            const bathroomsEl = document.getElementById('<?php echo $instance_id; ?>-bathrooms');
            const minPriceEl = document.getElementById('<?php echo $instance_id; ?>-min-price');
            const maxPriceEl = document.getElementById('<?php echo $instance_id; ?>-max-price');
            const useAPI = <?php echo $use_api ? 'true' : 'false'; ?>;
            
            let locations = [];
            let selectedIndex = -1;
            let filteredLocations = [];
            let debounceTimer = null;

            <?php if (!$use_api): ?>
            // Load locations asynchronously
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=pf_load_locations')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        locations = data.data;
                    }
                })
                .catch(err => console.error('Failed to load locations:', err));
            <?php endif; ?>

            // Location autocomplete
            inputEl.addEventListener('input', function() {
                const query = this.value.trim();
                hiddenEl.value = '';
                selectedIndex = -1;

                clearTimeout(debounceTimer);

                if (query.length < 2) {
                    suggestionsEl.style.display = 'none';
                    return;
                }

                <?php if ($use_api): ?>
                debounceTimer = setTimeout(() => {
                    suggestionsEl.innerHTML = '<div style="padding: 10px 15px; color: #666;">Searching...</div>';
                    suggestionsEl.style.display = 'block';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=pf_search_locations&term=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                suggestionsEl.innerHTML = '<div style="padding: 10px 15px; color: #999;">No locations found</div>';
                                return;
                            }
                            displaySuggestions(data.map(item => ({
                                id: item.id,
                                name: item.label
                            })));
                        })
                        .catch(err => {
                            console.error('Search failed:', err);
                            suggestionsEl.style.display = 'none';
                        });
                }, 300);
                <?php else: ?>
                const queryLower = query.toLowerCase();
                filteredLocations = locations.filter(loc =>
                    loc.name.toLowerCase().includes(queryLower)
                );

                if (filteredLocations.length === 0) {
                    suggestionsEl.innerHTML = locations.length === 0 
                        ? '<div style="padding: 10px 15px; color: #666;">Loading locations...</div>'
                        : '<div style="padding: 10px 15px; color: #999;">No locations found</div>';
                    suggestionsEl.style.display = 'block';
                    return;
                }

                displaySuggestions(filteredLocations);
                <?php endif; ?>
            });

            function displaySuggestions(locs) {
                let html = '';
                locs.forEach((loc, idx) => {
                    html += `<div class="pf-suggestion-item" data-index="${idx}" data-id="${loc.id}" data-name="${loc.name}">${loc.name}</div>`;
                });

                suggestionsEl.innerHTML = html;
                suggestionsEl.style.display = 'block';
                filteredLocations = locs;

                document.querySelectorAll('#<?php echo $instance_id; ?>-suggestions .pf-suggestion-item').forEach(item => {
                    item.addEventListener('click', function() {
                        selectLocation(this.dataset.id, this.dataset.name);
                    });
                });
            }

            inputEl.addEventListener('keydown', function(e) {
                const items = document.querySelectorAll('#<?php echo $instance_id; ?>-suggestions .pf-suggestion-item');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    updateSelection(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelection(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedIndex >= 0 && items[selectedIndex]) {
                        selectLocation(items[selectedIndex].dataset.id, items[selectedIndex].dataset.name);
                    } else {
                        performSearch();
                    }
                } else if (e.key === 'Escape') {
                    suggestionsEl.style.display = 'none';
                    selectedIndex = -1;
                }
            });

            function updateSelection(items) {
                items.forEach((item, idx) => {
                    if (idx === selectedIndex) {
                        item.style.background = '#f0f0f0';
                        item.scrollIntoView({ block: 'nearest' });
                    } else {
                        item.style.background = '#fff';
                    }
                });
            }

            function selectLocation(id, name) {
                inputEl.value = name;
                hiddenEl.value = id;
                suggestionsEl.style.display = 'none';
                selectedIndex = -1;
            }

            function performSearch() {
                const params = new URLSearchParams();
                
                if (hiddenEl.value) {
                    params.append('location-id', hiddenEl.value);
                }
                
                if (priceTypeEl.value) {
                    params.append('price-type', priceTypeEl.value);
                }
                
                if (propertyTypeEl.value) {
                    params.append('property-type', propertyTypeEl.value);
                }
                
                if (bedroomsEl.value) {
                    params.append('bedrooms', bedroomsEl.value);
                }
                
                if (bathroomsEl.value) {
                    params.append('bathrooms', bathroomsEl.value);
                }
                
                if (minPriceEl.value) {
                    params.append('min-price', minPriceEl.value);
                }
                
                if (maxPriceEl.value) {
                    params.append('max-price', maxPriceEl.value);
                }

                const queryString = params.toString();
                window.location.href = '/dev-25/property/' + (queryString ? '?' + queryString : '');
            }

            btnEl.addEventListener('click', performSearch);

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.pf-search-container')) {
                    suggestionsEl.style.display = 'none';
                    selectedIndex = -1;
                }
            });
        })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('pf_location_search', 'pf_location_search_shortcode');


/////////////////////////////

function pf_mobile_location_search_shortcode($atts) {
    $atts = shortcode_atts([
        'placeholder' => 'Search location...',
        'use_api' => 'false'
    ], $atts, 'pf_mobile_location_search');

    $instance_id = 'pf-mobile-search-' . uniqid();
    $use_api = filter_var($atts['use_api'], FILTER_VALIDATE_BOOLEAN);
    
    // Price options
    $price_options = [
        300000, 400000, 500000, 600000, 700000, 800000, 900000, 
        1000000, 1100000, 1200000, 1300000, 1400000, 1500000, 1600000, 
        1700000, 1800000, 1900000, 2000000, 2100000, 2200000, 2300000, 
        2400000, 2500000, 2600000, 2700000, 2800000, 2900000, 3000000, 
        3250000, 3500000, 3750000, 4000000, 4250000, 4500000, 4750000, 
        5000000, 6000000, 7000000, 8000000, 9000000, 10000000, 25000000, 50000000
    ];

    ob_start(); ?>

    <style>
        
    </style>

    <div class="pf-mobile-search-container">
        <!-- Search Bar with Location Input and Search Icon -->
        <div class="pf-mobile-search-bar">
            <div class="pf-mobile-location-wrapper">
                <input type="text"
                    id="<?php echo $instance_id; ?>-input"
                    placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                    autocomplete="off" />
                <input type="hidden" id="<?php echo $instance_id; ?>-location-id" value="" />
                <div id="<?php echo $instance_id; ?>-suggestions" class="pf-suggestions-dropdown"></div>
            </div>
            <button id="<?php echo $instance_id; ?>-search-icon" class="pf-mobile-search-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
        </div>

        <!-- Advanced Options Toggle -->
        <div id="<?php echo $instance_id; ?>-advanced-toggle" class="pf-mobile-advanced-toggle">
            <span>ADVANCED OPTIONS</span>
            <span id="<?php echo $instance_id; ?>-arrow" class="pf-mobile-advanced-arrow">▼</span>
        </div>

        <!-- Advanced Options (Collapsible) -->
        <div id="<?php echo $instance_id; ?>-advanced-options" class="pf-mobile-advanced-options">
            <div class="pf-mobile-filters-grid">
                <div class="pf-mobile-field">
                    <label for="<?php echo $instance_id; ?>-price-type">Price Type</label>
                    <select id="<?php echo $instance_id; ?>-price-type">
                        <option value="">Any</option>
                        <option value="buy">Sale</option>
                        <option value="rent">Rent</option>
                    </select>
                </div>

                <div class="pf-mobile-field">
                    <label for="<?php echo $instance_id; ?>-property-type">Property Type</label>
                    <select id="<?php echo $instance_id; ?>-property-type">
                        <option value="">Any Type</option>
                        <option value="apartment">Apartment</option>
                        <option value="land">Land</option>
                        <option value="office-space">Office Space</option>
                        <option value="penthouse">Penthouse</option>
                        <option value="townhouse">Townhouse</option>
                        <option value="villa">Villa</option>
                    </select>
                </div>

                <div class="pf-mobile-field">
                    <label for="<?php echo $instance_id; ?>-bedrooms">Bedrooms</label>
                    <select id="<?php echo $instance_id; ?>-bedrooms">
                        <option value="">Any</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="pf-mobile-field">
                    <label for="<?php echo $instance_id; ?>-bathrooms">Bathrooms</label>
                    <select id="<?php echo $instance_id; ?>-bathrooms">
                        <option value="">Any</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="pf-mobile-field">
                    <label for="<?php echo $instance_id; ?>-min-price">Min Price</label>
                    <select id="<?php echo $instance_id; ?>-min-price">
                        <option value="">No Min</option>
                        <?php foreach ($price_options as $price): ?>
                            <option value="<?php echo $price; ?>"><?php echo number_format($price); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pf-mobile-field">
                    <label for="<?php echo $instance_id; ?>-max-price">Max Price</label>
                    <select id="<?php echo $instance_id; ?>-max-price">
                        <option value="">No Max</option>
                        <?php foreach ($price_options as $price): ?>
                            <option value="<?php echo $price; ?>"><?php echo number_format($price); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Search Button (appears when advanced options are open) -->
            <button id="<?php echo $instance_id; ?>-search-btn" class="pf-mobile-search-btn">
                Search Properties
            </button>
        </div>
    </div>

    <script>
        (function() {
            const inputEl = document.getElementById('<?php echo $instance_id; ?>-input');
            const hiddenEl = document.getElementById('<?php echo $instance_id; ?>-location-id');
            const suggestionsEl = document.getElementById('<?php echo $instance_id; ?>-suggestions');
            const searchIconEl = document.getElementById('<?php echo $instance_id; ?>-search-icon');
            const searchBtnEl = document.getElementById('<?php echo $instance_id; ?>-search-btn');
            const advancedToggleEl = document.getElementById('<?php echo $instance_id; ?>-advanced-toggle');
            const advancedOptionsEl = document.getElementById('<?php echo $instance_id; ?>-advanced-options');
            const arrowEl = document.getElementById('<?php echo $instance_id; ?>-arrow');
            const priceTypeEl = document.getElementById('<?php echo $instance_id; ?>-price-type');
            const propertyTypeEl = document.getElementById('<?php echo $instance_id; ?>-property-type');
            const bedroomsEl = document.getElementById('<?php echo $instance_id; ?>-bedrooms');
            const bathroomsEl = document.getElementById('<?php echo $instance_id; ?>-bathrooms');
            const minPriceEl = document.getElementById('<?php echo $instance_id; ?>-min-price');
            const maxPriceEl = document.getElementById('<?php echo $instance_id; ?>-max-price');
            const useAPI = <?php echo $use_api ? 'true' : 'false'; ?>;
            
            let locations = [];
            let selectedIndex = -1;
            let filteredLocations = [];
            let debounceTimer = null;
            let isAdvancedOpen = false;

            <?php if (!$use_api): ?>
            // Load locations asynchronously
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=pf_load_locations')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        locations = data.data;
                    }
                })
                .catch(err => console.error('Failed to load locations:', err));
            <?php endif; ?>

            // Advanced options toggle
            advancedToggleEl.addEventListener('click', function() {
                isAdvancedOpen = !isAdvancedOpen;
                
                if (isAdvancedOpen) {
                    advancedOptionsEl.classList.add('open');
                    arrowEl.classList.add('open');
                    searchIconEl.classList.add('hidden');
                    searchBtnEl.classList.add('show');
                } else {
                    advancedOptionsEl.classList.remove('open');
                    arrowEl.classList.remove('open');
                    searchIconEl.classList.remove('hidden');
                    searchBtnEl.classList.remove('show');
                }
            });

            // Location autocomplete
            inputEl.addEventListener('input', function() {
                const query = this.value.trim();
                hiddenEl.value = '';
                selectedIndex = -1;

                clearTimeout(debounceTimer);

                if (query.length < 2) {
                    suggestionsEl.style.display = 'none';
                    return;
                }

                <?php if ($use_api): ?>
                debounceTimer = setTimeout(() => {
                    suggestionsEl.innerHTML = '<div style="padding: 10px 15px; color: #666;">Searching...</div>';
                    suggestionsEl.style.display = 'block';

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=pf_search_locations&term=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                suggestionsEl.innerHTML = '<div style="padding: 10px 15px; color: #999;">No locations found</div>';
                                return;
                            }
                            displaySuggestions(data.map(item => ({
                                id: item.id,
                                name: item.label
                            })));
                        })
                        .catch(err => {
                            console.error('Search failed:', err);
                            suggestionsEl.style.display = 'none';
                        });
                }, 300);
                <?php else: ?>
                const queryLower = query.toLowerCase();
                filteredLocations = locations.filter(loc =>
                    loc.name.toLowerCase().includes(queryLower)
                );

                if (filteredLocations.length === 0) {
                    suggestionsEl.innerHTML = locations.length === 0 
                        ? '<div style="padding: 10px 15px; color: #666;">Loading locations...</div>'
                        : '<div style="padding: 10px 15px; color: #999;">No locations found</div>';
                    suggestionsEl.style.display = 'block';
                    return;
                }

                displaySuggestions(filteredLocations);
                <?php endif; ?>
            });

            function displaySuggestions(locs) {
                let html = '';
                locs.forEach((loc, idx) => {
                    html += `<div class="pf-suggestion-item" data-index="${idx}" data-id="${loc.id}" data-name="${loc.name}">${loc.name}</div>`;
                });

                suggestionsEl.innerHTML = html;
                suggestionsEl.style.display = 'block';
                filteredLocations = locs;

                document.querySelectorAll('#<?php echo $instance_id; ?>-suggestions .pf-suggestion-item').forEach(item => {
                    item.addEventListener('click', function() {
                        selectLocation(this.dataset.id, this.dataset.name);
                    });
                });
            }

            inputEl.addEventListener('keydown', function(e) {
                const items = document.querySelectorAll('#<?php echo $instance_id; ?>-suggestions .pf-suggestion-item');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    updateSelection(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelection(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedIndex >= 0 && items[selectedIndex]) {
                        selectLocation(items[selectedIndex].dataset.id, items[selectedIndex].dataset.name);
                    } else {
                        performSearch();
                    }
                } else if (e.key === 'Escape') {
                    suggestionsEl.style.display = 'none';
                    selectedIndex = -1;
                }
            });

            function updateSelection(items) {
                items.forEach((item, idx) => {
                    if (idx === selectedIndex) {
                        item.style.background = '#f0f0f0';
                        item.scrollIntoView({ block: 'nearest' });
                    } else {
                        item.style.background = '#fff';
                    }
                });
            }

            function selectLocation(id, name) {
                inputEl.value = name;
                hiddenEl.value = id;
                suggestionsEl.style.display = 'none';
                selectedIndex = -1;
            }

            function performSearch() {
                const params = new URLSearchParams();
                
                if (hiddenEl.value) {
                    params.append('location-id', hiddenEl.value);
                }
                
                if (priceTypeEl.value) {
                    params.append('price-type', priceTypeEl.value);
                }
                
                if (propertyTypeEl.value) {
                    params.append('property-type', propertyTypeEl.value);
                }
                
                if (bedroomsEl.value) {
                    params.append('bedrooms', bedroomsEl.value);
                }
                
                if (bathroomsEl.value) {
                    params.append('bathrooms', bathroomsEl.value);
                }
                
                if (minPriceEl.value) {
                    params.append('min-price', minPriceEl.value);
                }
                
                if (maxPriceEl.value) {
                    params.append('max-price', maxPriceEl.value);
                }

                const queryString = params.toString();
                window.location.href = '/dev-25/property/' + (queryString ? '?' + queryString : '');
            }

            searchIconEl.addEventListener('click', performSearch);
            searchBtnEl.addEventListener('click', performSearch);

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.pf-mobile-search-container')) {
                    suggestionsEl.style.display = 'none';
                    selectedIndex = -1;
                }
            });
        })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('pf_mobile_location_search', 'pf_mobile_location_search_shortcode');

function replace_gtranslate_lang_names_with_codes_footer() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // Replace language names in the dropdown list
        var links = document.querySelectorAll('.gt_switcher .gt_option a');
        links.forEach(function(link) {
            var lang = link.getAttribute('data-gt-lang');
            if (lang) {
                var img = link.querySelector('img');
                if (img) {
                    link.innerHTML = '';
                    link.appendChild(img);
                    link.insertAdjacentText('beforeend', ' ' + lang);
                }
            }
        });

        // Replace language name in the selected/displayed button
        var selected = document.querySelector('.gt_selected a');
        if (selected) {
            var img = selected.querySelector('img');
            if (img) {
                var lang = img.getAttribute('alt');
                selected.innerHTML = '';
                selected.appendChild(img);
                selected.insertAdjacentText('beforeend', ' ' + lang);
            }
        }

        // Also observe for dynamic changes (GTranslate updates gt_selected after user picks a language)
        var observer = new MutationObserver(function() {
            var selected = document.querySelector('.gt_selected a');
            if (selected && selected.innerText.trim().length > 5) {
                var img = selected.querySelector('img');
                if (img) {
                    var lang = img.getAttribute('alt');
                    selected.innerHTML = '';
                    selected.appendChild(img);
                    selected.insertAdjacentText('beforeend', ' ' + lang);
                }
            }
        });

        var gtSelected = document.querySelector('.gt_selected');
        if (gtSelected) {
            observer.observe(gtSelected, { childList: true, subtree: true });
        }

    });
    </script>
    <?php
}
add_action('wp_footer', 'replace_gtranslate_lang_names_with_codes_footer', 10);


function offplan_developers_grid( $atts ) {
    $atts = shortcode_atts( array(
        'columns' => 4,
        'per_page' => -1,
    ), $atts );

    $terms = get_terms( array(
        'taxonomy'   => 'developer',
        'hide_empty' => false,
    ));

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return '<p>No developers found.</p>';
    }

    ob_start();
    ?>
    <div class="developers-grid" style="display:grid; grid-template-columns: repeat(<?php echo intval( $atts['columns'] ); ?>, 1fr); gap: 20px;">
        <?php foreach ( $terms as $term ) :
            $term_id     = $term->term_id;
            $logo        = get_field( 'logo', 'developer_' . $term_id );
            $description = wp_trim_words( $term->description, 20, '...' );
        ?>
        <div class="developer-item">
    <?php if ( $logo ) : ?>
        <div class="developer-logo">
            <img src="<?php echo esc_url( is_array( $logo ) ? $logo['url'] : $logo ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" decoding="async">
        </div>
    <?php endif; ?>
    <div class="developer-title">
        <h3><?php echo esc_html( $term->name ); ?></h3>
    </div>
    <div class="developer-desc">
        <p><?php echo esc_html( $description ); ?></p>
    </div>
    <div class="developer-btn">
        <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="developer-view-btn">
            View Properties
        </a>
    </div>
</div>  <!-- ✅ This closing tag was missing -->
<?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'developers_grid', 'offplan_developers_grid' );
