<?php

/**
 * Plugin Name:       YouTube Embed Extras
 * Plugin URI:        https://github.com/RyanNutt/wordpress-youtube-embed
 * Description:       Adds video open graph tags when a YouTube embed is found in post content
 * Version:           0.2.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Ryan Nutt
 * Author URI:        https://www.nutt.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aelora-youtube-embed
 */

namespace Aelora\WordPress\YouTube;

YouTubeEmbed::init();

class YouTubeEmbed
{

  public static function init()
  {

    spl_autoload_register([self::class, 'autoload']);
    require_once(__DIR__ . '/_inc/ext/PostTransient.php');
    Yoast::init();
    add_action('admin_init', [self::class, 'settings_init']);

    // add_filter('wpseo_metadesc', [self::class, 'empty_description']);

    add_action('wp_head', [self::class, 'json_ld']);

    add_filter('get_post_metadata', [self::class, 'postMetadata'], 99, 4);
    add_filter('get_page_metadata', [self::class, 'postMetadata'], 99, 4);

    $updater = new Smashing_Updater(__FILE__);
    $updater->set_username('ryannutt');
    $updater->set_repository('wordpress-youtube-embed');
    $updater->initialize();
  }

  /**
   * Filter for _thumbnail_id
   */
  public static function postMetadata($null, $objectID, $key, $single)
  {
    global $post;
    if ($key == '_thumbnail_id') {
      $allMeta = get_post_meta($post->ID);

      /* If it's there, don't override it */
      if (!isset($allMeta['_thumbnail_id'][0])) {
        if (YouTube::hasLink()) {
          $attachmentID = YouTube::thumbnailAttachment($post->ID, true);
          return $single ? $attachmentID : [$attachmentID];
        }
      }
    }
  }


  public static function autoload($classname)
  {
    if (strpos($classname, __NAMESPACE__) === 0) {
      $ray = explode("\\", $classname);
      $class = end($ray);
      if (file_exists(__DIR__ . '/_inc/classes/' . $class . '.php')) {
        require_once(__DIR__ . '/_inc/classes/' . $class . '.php');
      }
    }
  }

  public static function settings_init()
  {
    register_setting('media', 'aelora-youtube-tags-apikey', [self::class, 'setting_sanitize']);
    add_settings_section('aelora-youtube-tag-api', __('YouTube Tags', 'aelora-youtube-embed'), '__return_false', 'media');
    add_settings_field('aelora-youtube-tag-apikey', __('YouTube API Key', 'aelora-youtube-embed'), [self::class, 'field_callback'], 'media', 'aelora-youtube-tag-api');
  }

  public static function settings_sanitize($input)
  {
    return $input;
  }

  public static function field_callback()
  {
?>
    <label for="aelora-youtube-tags-apikey">
      <input id="aelora-youtube-tags-apikey" class="regular-text" type="text" value="<?php echo esc_attr(get_option('aelora-youtube-tags-apikey', '')); ?>" name="aelora-youtube-tags-apikey">
    </label>
<?php
  }

  /**
   * Build the JSON+LD fields for a single video post.
   * 
   * This will also pull from the YouTube API if needed to get the 
   * information needed. 
   * 
   * This happens after the og tags, so most of the API data is there.
   * But it's possible that some of the contentDetails fields haven't
   * been and they'll be pulled here if needed. 
   */
  public static function json_ld()
  {
    if (YouTube::hasLink()) {
      global $post;
      $yt = YouTube::postMeta($post->ID);
      if (!empty($yt)) {
        $json = [
          '@context' => 'http://schema.org',
          '@type' => 'VideoObject',
          '@id' => get_permalink($post->ID),
          'name' => get_the_title(),
          'description' => get_the_title(),
          'uploadDate' => get_the_date('c', $post->ID),
          'duration' => !empty($yt['contentDetails']['duration']) ? $yt['contentDetails']['duration'] : ''
        ];

        $attachment = YouTube::thumbnailAttachment($post->ID);
        if (!empty($attachment)) {
          $img = wp_get_attachment_image_src($attachment->ID, 'full');
          $thumb = [
            '@context' => 'http://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => $img[0],
            'width' => $img[1],
            'height' => $img[2]
          ];
          $json['thumbnail'] = $thumb;
          $json['thumbnailUrl'] = $img[0];
        }

        echo '<script type="application/ld+json">' . json_encode($json) . '</script>';
      }
    }
  }

  public static function empty_description($desc)
  {
    if (is_single() && empty($desc)) {
      global $post;
      return $post->post_title;
    }
    return $desc;
  }

  private static function has_youtube_link()
  {
    global $post;
    return is_single() && preg_match('~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s]+)~', $post->post_content);
  }
}
