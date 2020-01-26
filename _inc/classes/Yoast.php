<?php

namespace Aelora\WordPress\YouTube;

/**
 * Methods specific to the Yoast SEO plugin
 */
class Yoast
{
    public static function init()
    {
        add_action('wpseo_opengraph', [self::class, 'og_fields']);
        add_filter('wpseo_opengraph_type', [self::class, 'og_type']);
    }

    public static function og_fields()
    {
        if (!is_single() || !YouTube::hasLink()) {
            return;
        }
        global $post;
        $json = YouTube::postMeta($post->ID);
        $attachmentID = get_post_meta($post->ID, '_youtube_thumbnail_attachment', true);

        if (empty($json) || empty($attachmentID)) {
            /* Don't have the data we need, just bail */
            return;
        }
        /* Output the tags. These are in the header. */
        $img = wp_get_attachment_image_src($attachmentID, 'full');
        echo '<meta property="og:image" content="' . $img[0] . '">
        <meta property="og:image:width" content="' . $img[1] . '">
        <meta property="og:image:height" content="' . $img[2] . '">
        <meta property="og:video:url" content="https://www.youtube.com/watch?v=' . $json['id'] . '">
        <meta property="og:video:secure_url" content="https://www.youtube.com/watch?v=' . $json['id'] . '">
        <meta property="og:video:type" content="text/html">';
    }

    /**
     * Change the og type if there's a YouTube link in it. Oembed will take
     * care of the actual editor. 
     * 
     * @global type $post
     * @param type $type
     * @return string
     */
    public static function og_type($type)
    {
        if (!is_single()) {
            return $type;
        }

        global $post;

        if (preg_match('~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s]+)~', $post->post_content)) {
            return 'video';
        }
        return $type;
    }
}
