<?php
/**
 * Render callback for CPT Display Block
 */

function render_dianalund_cpt_display_block($attributes) {
    if (empty($attributes['postId'])) {
        return '<p>Intet indlæg valgt.</p>';
    }

    $post_id = intval($attributes['postId']);
    $post = get_post($post_id);
    
    if (!$post) {
        return '<p>Indlæg ikke fundet.</p>';
    }

    $permalink = get_permalink($post_id);
    $post_type = $post->post_type;
    
    $output = '<div class="dianalund-cpt-display">';
    
    // Logo og navn som link
    $logo_html = '';
    $name_html = '';
    
    if ($attributes['showLogo']) {
        $logo_id = get_post_meta($post_id, 'allround-cpt_logo_id', true);
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_src($logo_id, 'medium');
            if ($logo_url) {
                $logo_html = '<div class="cpt-logo"><img src="' . esc_url($logo_url[0]) . '" alt="' . esc_attr($post->post_title) . ' logo" /></div>';
            }
        }
    }
    
    if ($attributes['showName']) {
        $name_html = '<h3 class="cpt-name">' . esc_html($post->post_title) . '</h3>';
    }
    
    // Wrap logo og navn i link hvis en af dem skal vises
    if ($logo_html || $name_html) {
        $output .= '<a href="' . esc_url($permalink) . '" class="cpt-main-link" rel="bookmark" title="' . esc_attr($post->post_title) . '">';
        $output .= $logo_html . $name_html;
        $output .= '</a>';
    }
    
    // Social links
    $social_output = '';
    
    // Baseret på post type, hent de rigtige meta fields
    $facebook = '';
    $instagram = '';  
    $website = '';
    $email = '';
    
    switch ($post_type) {
        case 'butiksside':
            $facebook = get_post_meta($post_id, 'butik_payed_fb', true);
            $instagram = get_post_meta($post_id, 'butik_payed_insta', true);
            $website = get_post_meta($post_id, 'butik_payed_web', true);
            $email = get_post_meta($post_id, 'butik_payed_mail', true);
            break;
        case 'erhvervsside':
            $facebook = get_post_meta($post_id, 'bizz_payed_fb', true);
            $instagram = get_post_meta($post_id, 'bizz_payed_insta', true);
            $website = get_post_meta($post_id, 'bizz_payed_web', true);
            $email = get_post_meta($post_id, 'bizz_payed_mail', true);
            break;
        case 'foreningsside':
            $facebook = get_post_meta($post_id, 'fritid_payed_fb', true);
            $instagram = ''; // Foreninger har ikke Instagram felt
            $website = get_post_meta($post_id, 'fritid_payed_web', true);
            $email = get_post_meta($post_id, 'fritid_payed_mail', true);
            break;
    }
    
    if (!empty($attributes['showFacebook']) && $facebook) {
        $facebook_url = $facebook;
        // Tilføj https:// hvis der ikke er protokol
        if (!preg_match('/^https?:\/\//', $facebook_url)) {
            $facebook_url = 'https://' . $facebook_url;
        }
        $social_output .= '<a href="' . esc_url($facebook_url) . '" class="social-icon facebook" target="_blank" rel="noopener" title="Facebook">';
        $social_output .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 26 26"><path d="M23.214,2.25H2.786A2.786,2.786,0,0,0,0,5.036V25.464A2.786,2.786,0,0,0,2.786,28.25h7.965V19.411H7.095V15.25h3.656V12.079c0-3.607,2.147-5.6,5.436-5.6a22.15,22.15,0,0,1,3.222.281V10.3H17.595a2.08,2.08,0,0,0-2.346,2.248v2.7h3.992L18.6,19.411H15.249V28.25h7.965A2.786,2.786,0,0,0,26,25.464V5.036A2.786,2.786,0,0,0,23.214,2.25Z" transform="translate(0 -2.25)" fill="#4267B2"/></svg>';
        $social_output .= '</a>';
    }
    
    if (!empty($attributes['showInstagram']) && $instagram) {
        $instagram_url = $instagram;
        if (!preg_match('/^https?:\/\//', $instagram_url)) {
            $instagram_url = 'https://' . $instagram_url;
        }
        $social_output .= '<a href="' . esc_url($instagram_url) . '" class="social-icon instagram" target="_blank" rel="noopener" title="Instagram">';
        $social_output .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 26 26"><path d="M13,8.572a6.666,6.666,0,1,0,6.665,6.666A6.655,6.655,0,0,0,13,8.572Zm0,11a4.334,4.334,0,1,1,4.333-4.334A4.341,4.341,0,0,1,13,19.572ZM21.489,8.3a1.554,1.554,0,1,1-1.554-1.555A1.551,1.551,0,0,1,21.489,8.3ZM25.9,9.877a7.7,7.7,0,0,0-2.1-5.448,7.742,7.742,0,0,0-5.447-2.1c-2.146-.122-8.579-.122-10.725,0A7.731,7.731,0,0,0,2.186,4.423a7.721,7.721,0,0,0-2.1,5.448c-.122,2.147-.122,8.581,0,10.727a7.7,7.7,0,0,0,2.1,5.448,7.752,7.752,0,0,0,5.447,2.1c2.146.122,8.579.122,10.725,0a7.692,7.692,0,0,0,5.447-2.1A7.746,7.746,0,0,0,25.9,20.6c.122-2.147.122-8.575,0-10.721ZM23.131,22.9a4.387,4.387,0,0,1-2.471,2.471c-1.711.679-5.771.522-7.662.522s-5.957.151-7.662-.522A4.387,4.387,0,0,1,2.864,22.9c-.679-1.711-.522-5.773-.522-7.664s-.151-5.958.522-7.664A4.387,4.387,0,0,1,5.335,5.1C7.046,4.423,11.107,4.58,13,4.58s5.957-.151,7.662.522a4.387,4.387,0,0,1,2.471,2.471c.679,1.711.522,5.773.522,7.664S23.809,21.2,23.131,22.9Z" transform="translate(0.005 -2.238)" fill="#E4405F"/></svg>';
        $social_output .= '</a>';
    }
    
    if (!empty($attributes['showWebsite']) && $website) {
        $website_url = $website;
        if (!preg_match('/^https?:\/\//', $website_url)) {
            $website_url = 'https://' . $website_url;
        }
        $social_output .= '<a href="' . esc_url($website_url) . '" class="social-icon website" target="_blank" rel="noopener" title="Hjemmeside">';
        $social_output .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 29 29"><g transform="translate(-1.5 -1.5)"><path d="M29,16A13,13,0,1,1,16,3,13,13,0,0,1,29,16Z" fill="none" stroke="#333" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/><path d="M3,18H29" transform="translate(0 -2)" fill="none" stroke="#333" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/><path d="M18,3a18.555,18.555,0,0,1,6,13,18.555,18.555,0,0,1-6,13,18.555,18.555,0,0,1-6-13A18.555,18.555,0,0,1,18,3Z" transform="translate(-2)" fill="none" stroke="#333" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/></g></svg>';
        $social_output .= '</a>';
    }
    
    if (!empty($attributes['showEmail']) && $email) {
        $social_output .= '<a href="mailto:' . esc_attr($email) . '" class="social-icon email" title="Send email">';
        $social_output .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="18" viewBox="0 0 34.667 26"><path d="M34.01,13.085a.408.408,0,0,1,.657.318V27.25a3.251,3.251,0,0,1-3.25,3.25H3.25A3.251,3.251,0,0,1,0,27.25V13.41a.4.4,0,0,1,.657-.318c1.517,1.178,3.528,2.674,10.434,7.692,1.429,1.043,3.839,3.236,6.243,3.223,2.417.02,4.875-2.221,6.249-3.223C30.489,15.767,32.493,14.264,34.01,13.085ZM17.333,21.833c1.571.027,3.832-1.977,4.97-2.8,8.985-6.52,9.669-7.089,11.741-8.714a1.62,1.62,0,0,0,.623-1.28V7.75a3.251,3.251,0,0,0-3.25-3.25H3.25A3.251,3.251,0,0,0,0,7.75V9.036a1.63,1.63,0,0,0,.623,1.28c2.072,1.618,2.756,2.194,11.741,8.714C13.5,19.856,15.762,21.86,17.333,21.833Z" transform="translate(0 -4.5)" fill="#D44638"/></svg>';
        $social_output .= '</a>';
    }
    
    if ($social_output) {
        $output .= '<div class="cpt-social-links">' . $social_output . '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
?>
