<?php

/**
 * Widget to display Google Photos from a public album.
 */
class Simple_Google_Photos_Grid_Widget extends WP_Widget
{

  /**
   * Default length (in minutes) to cache the photo urls retrieved from Google
   */
  const CACHE_INTERVAL = 15;

  /**
   * Default number of photos to display in the widget
   */
  const NUMBER_PHOTOS = 4;

  /**
   * Register widget with WordPress.
   */
  function __construct()
  {
    parent::__construct(
      self::name(),
      __( ucwords(str_replace('-', ' ', self::name())), self::name() ),
      [ 'description' => __( 'Show latest photos from a public Google Photos album', self::name() ), ]
    );
  }

  /**
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget( $args, $instance )
  {
    echo $args['before_widget'];
    if ( ! empty( $instance['title'] ) )
    {
      echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
    }

    $cache_interval = $instance['cache-interval'] ? $instance['cache-interval'] : self::CACHE_INTERVAL;
    $num_photos = $instance['number-photos'] ? $instance['number-photos'] : self::NUMBER_PHOTOS;

    $photos = $this->get_photos($instance['album-url'], $cache_interval);

    $html = '<style>'.$this->widget_css().'</style>';
    $html .= '<div id="'.$this->id_base.'">';
    foreach(array_slice($photos, 0, $num_photos) as $i => $photo) {
      $html .= '<div class="'.self::name() . '-cell">';
      $html .= '<a href="'.$instance['album-url'].'" target="_blank"><img src="'.$photo.'" alt="" class="'.self::name().'-image"></a>';
      $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<script>'.$this->widget_js().'</script>';
    echo $html;

    echo $args['after_widget'];
  }

  /**
   * Back-end widget form.
   *
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   * @return string|void
   */
  public function form( $instance ) {
    if($instance['album-url']) {
      $album_url = $instance['album-url'];
      $title = $instance['title'];
      $cache_interval = $instance['cache-interval'];
      $num_photos = $instance['number-photos'];
    }
    else {
      $album_url = '';
      $title = '';
      $cache_interval = self::CACHE_INTERVAL;
      $num_photos = self::NUMBER_PHOTOS;
    }
    ?>
    <p>
      <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
    </p>
    <p>
      <label for="<?php echo $this->get_field_id( 'album-url' ); ?>"><?php _e( 'Album URL:' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id( 'album-url' ); ?>" name="<?php echo $this->get_field_name( 'album-url' ); ?>" type="url" value="<?php echo esc_attr( $album_url ); ?>">
    </p>
    <p>
      <label for="<?php echo $this->get_field_id( 'number-photos' ); ?>"><?php _e( 'Num Photos to Show:' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id( 'number-photos' ); ?>" name="<?php echo $this->get_field_name( 'number-photos' ); ?>" type="number" min="1" step="1" value="<?php echo esc_attr( $num_photos ); ?>">
    </p>
    <p>
      <label for="<?php echo $this->get_field_id( 'cache-interval' ); ?>"><?php _e( 'Cache Interval (minutes):' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id( 'cache-interval' ); ?>" name="<?php echo $this->get_field_name( 'cache-interval' ); ?>" type="number" min="0" step="1" value="<?php echo esc_attr( $cache_interval ); ?>">
    </p>
  <?php
  }

  /**
   * Sanitize widget form values as they are saved.
   *
   * @see WP_Widget::update()
   *
   * @param array $new_instance Values just sent to be saved.
   * @param array $old_instance Previously saved values from database.
   *
   * @return array Updated safe values to be saved.
   */
  public function update( $new_instance, $old_instance ) {
    $instance = [];
    $instance['title'] = strip_tags($new_instance['title']);
    $instance['cache-interval'] = intval($new_instance['cache-interval']);
    $instance['number-photos'] = intval($new_instance['number-photos']);
    $instance['album-url'] = esc_url_raw( $new_instance['album-url'], ['https'] );

    return $instance;
  }

  /**
   * Retrieve photos from cache or from google
   *
   * @param string $album_url A google photos album short or long url
   * @param integer $cache_interval Length in minutes to cache (0) for no cache
   *
   * @return array
   */
  protected function get_photos($album_url, $cache_interval) {
    $album = get_option($this->album_option_name($album_url));
    if($album &&
      (isset($album['photos']) && !empty($album['photos'])) &&
      (isset($album['cache-time']) && ($album['cache-time'] + ($cache_interval * 60) > time()))) {
        $photos = $album['photos'];
    }
    else {
      $photos = $this->get_photos_from_google($album_url);
      if($cache_interval) {
        $this->cache_album($album_url, $photos);
      }
    }
    return $photos;
  }

  /**
   * Hackety-hack way to retrieve photos from a public album since google has no working api for google photos
   * Read: https://kunnas.com/google-photos-is-a-disaster/
   * And: https://productforums.google.com/forum/#!topic/photos/WuqfNazcqh4
   *
   * @param $album_url
   *
   * @return array
   */
  protected function get_photos_from_google($album_url) {
    $photos = [];
    $response = wp_remote_get( $album_url );
    if ( !is_wp_error( $response ) ) {
      $body = $response['body'];
      preg_match_all('@\["AF1Q.*?",\["(.*?)"\,@', $body, $urls);
      if(isset($urls[1])) $photos = $urls[1];
    }
    return $photos;
  }

  /**
   * A unique name for the widget option, per album
   *
   * @param $album_url
   *
   * @return string
   */
  protected function album_option_name($album_url) {
    return self::name() . '-' . md5($album_url);
  }

  /**
   * "Cache" the album urls in the options table
   * @param $album_url
   * @param $photos
   */
  protected function cache_album($album_url, $photos) {
    $option_value = [
      'cache-time' => time(),
      'photos' => $photos
    ];
    add_option($this->album_option_name($album_url), $option_value);
  }

  /**
   * Style block CSS for the widget, why not?
   *
   * @return string
   */
  protected function widget_css() {
    $cell_class = self::name() . '-cell';
    $image_class = self::name() . '-image';

    return <<<EOD
      div#{$this->id_base} {
        width:100%;
        height:100%;
        overflow:hidden;      
      }
      div.{$cell_class} {
        box-sizing:border-box;
        padding:5px;
        float:left;
        width:50%;
      }
      img.{$image_class} {
        object-fit: cover;
      }
EOD;
  }

  /**
   * Script block js for the widget, why not?
   *
   * @return string
   */
  protected function widget_js() {

    $cell_class = self::name() . '-cell';
    $image_class = self::name() . '-image';

    return <<<EOD
      (function() {
        if( window.jQuery ){
          var width = jQuery("div.{$cell_class}").first().width();
          jQuery("img.{$image_class}").css("width", width).css("height", width);
        }
      })();
EOD;
  }

  /**
   * Hook to run when uninstalling the plugin
   */
  public static function uninstall() {
    global $wpdb;

    $wpdb->query(
      "DELETE FROM $wpdb->options  WHERE `option_name` LIKE '%".self::name()."%'"
    );
  }

  /**
   * Used frequently
   *
   * @return string
   */
  public static function name() {
    return basename(__DIR__);
  }
}
