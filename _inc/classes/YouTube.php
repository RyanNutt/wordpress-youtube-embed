<?php

namespace Aelora\WordPress\YouTube;

use Aelora\WordPress\PostTransient;

/**
 * Class for working with the YouTube API
 */
class YouTube
{
    public static function apiCall($videoID, $parts = 'snippet,contentDetails')
    {
        $url = 'https://www.googleapis.com/youtube/v3/videos?id=' . $videoID . '&key=' . get_option('aelora-youtube-tags-apikey', '') . '&part=' . $parts;

        return json_decode(wp_remote_retrieve_body(wp_remote_get(esc_url_raw($url))), true);
    }

    public static function hasLink()
    {
        global $post;
        return is_single() && preg_match('~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s]+)~', $post->post_content);
    }

    /**
     * Gets the YouTube data from transient meta, if it exists. If it does not exist
     * this will make an API call to get the data and then store it in a post
     * transient meta field. 
     * 
     * @param integer $postID
     */
    public static function postMeta($postID)
    {
        if (!self::hasLink()) {
            return '';
        }

        $transient = PostTransient::get($postID, 'youtube_json', '');
        if (empty($transient)) {
            $videoID = self::getID($postID);
            $api = self::apiCall($videoID);
            if (!empty($api['items'][0])) {
                $transient = $api['items'][0];
                PostTransient::set($postID, 'youtube_json', $transient);
            }
        }
        return $transient;
    }

    /**
     * Gets the id for the YouTube video embedded in the post specified, or
     * false if there's not one.
     * 
     * @param type $post_id
     */
    public static function getID($post_id)
    {

        $post = get_post($post_id);
        if (preg_match('~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s"\']+)~', $post->post_content, $matches)) {
            if (!empty($matches[1])) {
                update_post_meta($post_id, 'youtube_id', $matches[1]);
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Returns the attachment that holds the thumbnail from YouTube if one exists, or false
     * if one does not.
     * 
     * If the post has an embedded YouTube video, but doesn't have an attachment this will
     * try and download the thumbnail and create the attachment. 
     */
    public static function thumbnailAttachment($postID)
    {
        $attachmentID = get_post_meta($postID, '_youtube_thumbnail_attachment', true);
        if (empty($attachmentID)) {
            $json = self::postMeta($postID);

            if (!empty($json['snippet']['thumbnails'])) {
                /* Find the largest thumbnail. That's the attachment. */
                $firstKey = array_keys($json['snippet']['thumbnails'])[0];
                $largest = $json['snippet']['thumbnails'][$firstKey];

                foreach ($json['snippet']['thumbnails'] as $key => $thumb) {
                    if ($thumb['width'] * $thumb['height'] > $largest['width'] * $largest['height']) {
                        $largest = $thumb;
                    }
                }

                if (!empty($largest['url'])) {
                    $remoteImage = new KM_Download_Remote_Image($largest['url'], [
                        'title' => sprintf(__('YouTube thumbnail %s'), !empty($json['id']) ? $json['id'] : 'unknown')
                    ]);
                    $attachmentID = $remoteImage->download();
                }
                if (empty($attachmentID)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        echo "\n<!-- " . __('Could not create file for remote YouTube download. Check permissions in your upload folders.') . " -->\n";
                    }
                    return false;
                }

                update_post_meta($postID, '_youtube_thumbnail_attachment', $attachmentID);
            }
        }
        if (empty($attachmentID)) {
            /* Still no id, need to return something to catch */
            return false;
        }
        return get_post($attachmentID);
    }
}
