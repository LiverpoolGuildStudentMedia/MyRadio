<?php
/**
 * Provides the MyURY_PlaylistsDaemon class for MyURY
 * @package MyURY_Daemon
 */

/**
 * This Daemon updates the auto-generated iTones Playlists once an hour.
 * 
 * @version 20130710
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package MyURY_Tracklist
 * @uses \Database
 * 
 */
class MyURY_PlaylistsDaemon {
  private static $lastrun = 0;
  
  public static function isEnabled() { return false; }
  
  public static function run() {
    if (self::$lastrun > time() - 3600) return;
    
    self::updateMostPlayedPlaylist();
    self::updateNewestUploadsPlaylist();
    
    //Done
    self::$lastrun = time();
  }
  
  private static function updateMostPlayedPlaylist() {
    $most_played = MyURY_TracklistItem::getTracklistStatsForBAPS(time() - (86400 * 7)); //Track play stats for last week
    
    $playlist = array();
    for ($i = 0; $i < 100; $i++) {
      if (!isset($most_played[$i])) break; //If there aren't that many, oh well.
      $playlist[] = MyURY_Track::getInstance($most_played[$i]['trackid']);
    }
    
    var_dump($playlist);
  }
  
  private static function updateNewestUploadsPlaylist() {
    $newest_tracks = NIPSWeb_AutoPlaylist::findByName('Newest Tracks')->getTracks();
    
    var_dump($newest_tracks);
  }
}