<?php
/**
 * Plugin Name:       WPSEO YouTube Embed OpenGraph Tags
 * Plugin URI:        https://www.nutt.net
 * Description:       Adds video open graph tags when a YouTube embed is found in post content
 * Version:           0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Ryan Nutt
 * Author URI:        https://www.nutt.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpseo-youtube-embed
 */

namespace Aelora\WordPress\YouTube;

YouTubeEmbed::init();

if ( ! class_exists( 'Smashing_Updater' ) ) {
  include_once( plugin_dir_path( __FILE__ ) . 'updater.php' );
}
$updater = new Smashing_Updater( __FILE__ );
$updater->set_username( 'ryannutt' );
$updater->set_repository( 'wordpress-wpseo-og-video' );
$updater->initialize();

class YouTubeEmbed {

  public static function init() {
    add_action( 'admin_init', [ self::class, 'settings_init' ] );

    add_filter( 'wpseo_opengraph_type', [ self::class, 'og_type' ] );
    add_filter( 'wpseo_metadesc', [ self::class, 'empty_description' ] );
    add_action( 'wpseo_opengraph', [ self::class, 'og_fields' ] );

    add_action( 'wp_head', [ self::class, 'json_ld' ] );
  }

  public static function settings_init() {
    register_setting( 'media', 'aelora-youtube-tags-apikey', [ self::class, 'setting_sanitize' ] );
    add_settings_section( 'aelora-youtube-tag-api', __( 'YouTube Tags', 'wpseo-youtube-embed' ), '__return_false', 'media' );
    add_settings_field( 'aelora-youtube-tag-apikey', __( 'YouTube API Key', 'wpseo-youtube-embed' ), [ self::class, 'field_callback' ], 'media', 'aelora-youtube-tag-api' );
  }

  public static function settings_sanitize( $input ) {
    return $input;
  }

  public static function field_callback() {
    ?>
    <label for="aelora-youtube-tags-apikey">
      <input id="aelora-youtube-tags-apikey" class="regular-text" type="text" value="<?php echo esc_attr( get_option( 'aelora-youtube-tags-apikey', '' ) ); ?>" name="aelora-youtube-tags-apikey">
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
  public static function json_ld() {
    if ( self::has_youtube_link() ) {
      global $post;
      $json_data = [
          '@context' => 'http://schema.org',
          '@type' => 'VideoObject',
          '@id' => get_permalink( $post->ID ),
          'name' => get_the_title(),
          'description' => get_the_title(),
          'uploadDate' => get_the_date( 'c', $post->ID )
      ];

      // So we don't have to make multiples later
      $api_call = false;

      $meta = get_post_meta( $post->ID, '', true );

      if ( ! empty( $meta[ 'youtube_thumb' ][ 0 ] ) ) {
        $json_data[ 'thumbnailUrl' ] = $meta[ 'youtube_thumb' ][ 0 ];
      }
      if ( ! empty( $meta[ 'youtube_thumb_height' ][ 0 ] ) ) {
        $json_data[ 'height' ] = (int) $meta[ 'youtube_thumb_height' ][ 0 ];
      }
      if ( ! empty( $meta[ 'youtube_thumb_width' ][ 0 ] ) ) {
        $json_data[ 'width' ] = (int) $meta[ 'youtube_thumb_width' ][ 0 ];
      }

      if ( empty( $meta[ 'youtube_duration' ][ 0 ] ) ) {
        $api_call = self::api_call( self::get_youtube_id( $post->ID ), 'contentDetails' );
        if ( ! empty( $api_call[ 'items' ][ 0 ][ 'contentDetails' ][ 'duration' ] ) ) {
          $json_data[ 'duration' ] = $api_call[ 'items' ][ 0 ][ 'contentDetails' ][ 'duration' ];
          update_post_meta( $post->ID, 'youtube_duration', $api_call[ 'items' ][ 0 ][ 'contentDetails' ][ 'duration' ] );
        }
      }
      else {
        $json_data[ 'duration' ] = $meta[ 'youtube_duration' ][ 0 ];
      }

      //print_r($meta); 
      echo '<script type="application/ld+json">' . json_encode( $json_data ) . '</script>';
    }
  }

  /**
   * Make and return results from an API call to YouTube for a video
   * 
   * @param string $youtubd_id
   * @param type $parts
   */
  private static function api_call( $youtube_id, $parts = 'snippet,contentDetails' ) {
    $url = 'https://www.googleapis.com/youtube/v3/videos?id=' . $youtube_id . '&key=' . get_option( 'aelora-youtube-tags-apikey', '' ) . '&part=' . $parts;

    return json_decode( wp_remote_retrieve_body( wp_remote_get( esc_url_raw( $url ) ) ), true );
  }

  /**
   * Gets the id for the YouTube video embedded in the post specified, or
   * false if there's not one.
   * 
   * Typically this will be in the youtube_id meta field, but since every
   * post isn't going to have that field we'll look in the post content
   * for a regex match as well. If neither is found, it'll return false. 
   * 
   * @param type $post_id
   */
  private static function get_youtube_id( $post_id ) {
    $meta = get_post_meta( $post_id, 'youtube_id', true );
    if ( ! empty( $meta ) ) {
      return $meta;
    }

    $post = get_post( $post_id );
    if ( preg_match( '~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s]+)~', $post->post_content, $matches ) ) {
      if ( ! empty( $matches[ 1 ] ) ) {
        update_post_meta( $post_id, 'youtube_id', $matches[ 1 ] );
        return $matches[ 1 ];
      }
    }

    return false;
  }

  public static function empty_description( $desc ) {
    if ( is_single() && empty( $desc ) ) {
      global $post;
      return $post->post_title;
    }
    return $desc;
  }

  private static function has_youtube_link() {
    global $post;
    return is_single() && preg_match( '~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s]+)~', $post->post_content );
  }

  /**
   * Change the og type if there's a YouTube link in it. Oembed will take
   * care of the actual editor. 
   * 
   * @global type $post
   * @param type $type
   * @return string
   */
  public static function og_type( $type ) {
    if ( ! is_single() ) {
      return $type;
    }

    global $post;

    if ( preg_match( '~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s]+)~', $post->post_content ) ) {
      return 'video';
    }
    return $type;
  }

  /**
   * Action callback to output extra open graph tags if there is a video
   * embedded on this page. 
   */
  public static function og_fields() {

    if ( ! is_single() ) {
      return;
    }

    global $post;
    if ( preg_match( '~(?:https?://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?([^\s]+)~', $post->post_content, $matches ) ) {
      if ( ! empty( $matches[ 0 ] ) ) {

        /* Since there's going to be cases where the thumb url isn't
         * empty, but the tags may be we'll use this to trap.
         */
        $api_call = false;

        // Get the url for the thumbnail via API call
        $thumb_url = get_post_meta( $post->ID, 'youtube_thumb', true );
        $thumb_url = false;
        if ( empty( $thumb_url ) ) {
          $api_call = json_decode( wp_remote_retrieve_body( wp_remote_get( esc_url_raw( 'https://www.googleapis.com/youtube/v3/videos?key=' . get_option( 'aelora-youtube-tags-apikey', '' ) . '&part=snippet&id=' . $matches[ 1 ] ) ) ), true );

          if ( ! empty( $api_call[ 'error' ] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
              if ( ! empty( $api_call[ 'error' ][ 'message' ] ) ) {
                echo "\n<!-- " . $api_call[ 'error' ][ 'message' ] . " -->\n";
              }
              else {
                echo "\n<!-- Double check your YouTube API key -->\n";
              }
            }
            return;
          }

          /* This thumb should always be there, so we're going to
           * use it as a fallback. 
           */
          $thumb_url = 'https://i.ytimg.com/vi/' . $matches[ 1 ] . '/0.jpg';
          $thumb_height = 360;
          $thumb_width = 480;

          if ( ! empty( $api_call[ 'items' ][ 0 ][ 'snippet' ][ 'thumbnails' ] ) ) {
            $thumbs = $api_call[ 'items' ][ 0 ][ 'snippet' ][ 'thumbnails' ];
            if ( ! empty( $thumbs[ 'maxres' ] ) ) {
              $thumb_url = $thumbs[ 'maxres' ][ 'url' ];
              $thumb_width = $thumbs[ 'maxres' ][ 'width' ];
              $thumb_height = $thumbs[ 'maxres' ][ 'height' ];
            }
            else if ( ! empty( $thumbs[ 'standard' ] ) ) {
              $thumb_url = $thumbs[ 'standard' ][ 'url' ];
              $thumb_width = $thumbs[ 'standard' ][ 'width' ];
              $thumb_height = $thumbs[ 'standard' ][ 'height' ];
            }
            else if ( ! empty( $thumbs[ 'high' ] ) ) {
              $thumb_url = $thumbs[ 'high' ][ 'url' ];
              $thumb_width = $thumbs[ 'high' ][ 'width' ];
              $thumb_height = $thumbs[ 'high' ][ 'height' ];
            }
            else if ( ! empty( $thumbs[ 'default' ] ) ) {
              $thumb_url = $thumbs[ 'default' ][ 'url' ];
              $thumb_width = $thumbs[ 'default' ][ 'width' ];
              $thumb_height = $thumbs[ 'default' ][ 'height' ];
            }
          }

          update_post_meta( $post->ID, 'youtube_thumb', $thumb_url );
          update_post_meta( $post->ID, 'youtube_thumb_height', $thumb_height );
          update_post_meta( $post->ID, 'youtube_thumb_width', $thumb_width );
        }
        else {
          $thumb_url = get_post_meta( $post->ID, 'youtube_thumb', true );
          $thumb_height = get_post_meta( $post->ID, 'youtube_thumb_height', true );
          $thumb_width = get_post_meta( $post->ID, 'youtube_thumb_width', true );
        }

        /* Output the tags. These are in the header. */
        echo '<meta property="og:image" content="' . $thumb_url . '">
        <meta property="og:video:url" content="' . $matches[ 0 ] . '">
        <meta property="og:video:secure_url" content="' . $matches[ 0 ] . '">
        <meta property="og:video:type" content="text/html">
        <meta property="og:video:width" content="' . $thumb_width . '">
        <meta property="og:video:height" content="' . $thumb_height . '">' . "\n";
      }
    }
  }

}
