<?php
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * ZINA (Zina is not Andromeda)
 *
 * Zina is a graphical interface to your MP3 collection, a personal
 * jukebox, an MP3 streamer. It can run on its own, embeded into an
 * existing website, or as a Drupal/Joomla/Wordpress/etc. module.
 *
 * http://www.pancake.org/zina
 * Author: Ryan Lathouwers <ryanlath@pacbell.net>
 * Support: http://sourceforge.net/projects/zina/
 * License: GNU GPL2 <http://www.gnu.org/copyleft/gpl.html>
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

#TODO: some of these are not needed anymore...
$titles = array(
	'dir_tags' => zt('Use ID3 album titles (if they exist)'),
	'settings_override' => zt('Allow override of settings on a directory-by-directory basis'),
	'settings_override_file' => zt('File name of directory override file'),
	'cat_various_lookahead' => zt('Expand "Various" directories'),
	'timezone' => zt('Timezone'),
	'genres_custom'=> zt('Allow Custom Genres'),
	'mp3_dir'=> zt('Music Directory'),
	'charset'=> zt('HTML Meta Character Set'),
	'zina_dir_abs'=> zt('Zina Files Directory (full path)<br>(Windows use forward slash, e.g. F:/_zina)'),
	'zina_dir_rel'=> zt('Zina Files Directory'),
	'lang'=> zt('Language'),
	'theme'=> zt('Theme'),
	'image_captions'=> zt('Look for and display image captions'),
	'amg'=> zt('Show amg.com search box'),
	'main_dir_title'=> zt('Title of Main Directory'),
	'play'=> zt('Allow playing of files'),
	'play_sel'=> zt('Show option to "play selected"'),
	'play_rec'=> zt('Show option to "play recursive"'),
	'play_rec_rand'=> zt('Show option to "play recursive random"'),
	'search'=> zt('Show search form'),
	'download'=> zt('Allow downloads'),
	'files_sort'=> zt('Sort files'),
	'genres'=> zt('Genre functionality'),
	'genres_images'=> zt('Show genre images'),
	'genres_cols'=> zt('Number of columns on the genres page'),
	'genres_split'=> zt('Split genre page into different pages'),
	'genres_pp'=> zt('Number of items per split page'),
	'genres_truncate'=> zt('Limit genre names to'),
	'playlists'=> zt('Allow custom playlists to be created'),
	'session_pls'=> zt('Allow everyone to have a temporary, session-based playlist'),
	'pls_user'=> zt('Allow authenticated users to create saved playlists'),
	'pls_public'=> zt('Allow playlists to be made public'),
	'pls_limit'=> zt('Maximum number of playlists per user'),
	'pls_ratings'=> zt('Allow rating of playlists'),
	'pls_included'=> zt('Show "Playlists Tracks Appear On"'),
	'pls_included_limit'=> zt('Number of playlists to show in "Playlists Tracks Appear On" list'),
	'pls_length_limit'=> zt('Maximum items per playlist'),
	'pls_tags'=> zt('Allowable HTML tags in playlist description'),
	'cms_tags'=> zt('Allowable HTML tags for CMS Editors'),
	'cms_editor'=> zt('Allow your CMS to have editors for artist/album/song descriptions, etc.'),
	'asx'=> zt('Return .asx instead of .m3u playlists'),
	'random'=> zt('Show play random option'),
	'ran_opts'=> zt('Random Number Options'),
	'ran_opts_def'=> zt('Default random number "selected"'),
	'honor_custom'=> zt('Have random play use custom playlists'),
	'cache'=> zt('Main Cache (for custom playlists, faster random playlists, genres)'),
	'cache_expire'=> zt('Main Cache life'),
	'timeout'=> zt('Script timeout (in seconds)'),
	'ext_graphic'=> zt('Extensions of graphic files to display'),
	'local_path'=> zt('Use local paths when running Zina locally'),
	'adm_name'=> zt('Administrator user name'),
	'loc_is_adm'=> zt('If running on local machine, user is admin'),
	'adm_ip'=> zt('Remote IP is admin'),
	'adm_ips'=> zt('If remote IP is found in this string, remote user is admin'),
	'apache_auth'=> zt('Append Apache user:password to playlist urls'),
	'stream_int'=> zt('Stream internally'),
	'stream_extinf'=> zt('Add ID3 info to playlists'),
	'stream_extinf_limit'=> zt('Limit ID3 playlist references (performance)'),
	'dir_file'=> zt('Name of description file for directories'),
	'dir_genre_look'=> zt('Show genre on a artist page by looking at an album genre'),
	'dir_skip'=> zt('Dirs with this prefix are not displayed'),
	'dir_sort_ignore'=> zt('Ignore string at beginning of directory name for sorting purposes'),
	'dir_si_str'=> zt('String to ignore'),
	'cat_auto'=> zt('Automatically theme a directory as a "category" if directories are greater than next item'),
	'cat_auto_num'=> zt('Number of directories to automatically make a "category"'),
	'cat_file'=> zt('Name of file that makes a directory a "category"'),
	'cat_cols'=> zt('Number of columns on a "category" page'),
	'cat_split'=> zt('Category Display'),
	'cat_sort'=> zt('Allow category pages to be sorted by Alpha or Date'),
	'cat_sort_default'=> zt('Default category sort'),
	'cat_pp'=> zt('Number of items per split page'),
	'cat_truncate'=> zt('Limit directory names to'),
	'cat_images'=> zt('Show images'),
	'dir_list'=> zt('Display sub-directory listing'),
	'dir_list_sort'=> zt('Sort sub-directory by year'),
	'dir_list_sort_asc'=> zt('Sort sub-directory ascending'),
	'subdir_images'=> zt('Display sub-directory images'),
	'subdir_cols'=> zt('Number of columns'),
	'subdir_truncate'=> zt('Limit subdirectory titles to'),
	'new_highlight'=> zt('Highlight new files and directories'),
	'new_time'=> zt('Highlight within'),
	'alt_dirs'=> zt('Alternate/Related Directories'),
	'alt_file'=> zt('Filename that contains list'),
	'mp3_info'=> zt('Get filesize, length, kpbs, time from mp3 files'),
	'mp3_info_faster'=> zt('Get mp3 info faster (slightly less accurate)'),
	'mp3_id3'=> zt('Use ID3v1/2 song titles (if they exist)'),
	'song_blurbs'=> zt('Check for song description'),
	'various'=> zt('Display song title as "Artist - Title"'),
	'various_file'=> zt('Name of file to make a directory list as "Artist - Title"'),
	'various_above'=> zt('Also display "Artist -Title" in directories below file'),
	'remote'=> zt('Look for "Remote" songs'),
	'remote_ext'=> zt('Remote file extension'),
	'fake'=> zt('Look for "Placeholder" songs'),
	'fake_ext'=> zt('Placeholder file extension'),
	'low'=> zt('Look for second (lower quality) mp3 with following suffix'),
	'low_suf'=> zt('Suffix for lo-fi mp3 name[suffix].ext; e.g. "song.lofi.mp3"'),
	'low_lookahead'=> zt('Lofi lookahead'),
	'resample'=> zt('On-the-Fly Resampling'),
	'enc_arr'=> zt('Encoding program'),
	'res_in_types'=> zt('Input resize types'),
	'res_out_type'=> zt('Output resize type'),
	#todo: could probably look some of this
	'res_dir_qual'=> zt('Diirectory JPEG quality (1=worst/100=best)'),
	'res_dir_img'=> zt('Directory images'),
	'res_dir_x'=> zt('Directory scale to width'),
	'res_sub_qual'=> zt('Sub-directory JPEG quality (1=worst/100=best)'),
	'res_sub_img'=> zt('Sub-directory images'),
	'res_sub_x'=> zt('Sub-directory scale to width'),
	'res_full_img'=> zt('Large size directory images'),
	'res_full_qual'=> zt('Large size directory JPEG quality (1=worst/100=best)'),
	'res_full_x'=> zt('Large size directory scale to width'),
	'res_genre_qual'=> zt('Genre JPEG quality (1=worst/100=best)'),
	'res_genre_img'=> zt('Genre images'),
	'res_genre_x'=> zt('Genre scale to width'),
	'res_search_qual'=> zt('Search JPEG quality (1=worst/100=best)'),
	'res_search_img'=> zt('Resize search images'),
	'res_search_x'=> zt('Search scale to width'),
	'res_out_x_lmt'=> zt('Only rescale if original width > rescale width'),
	'dir_img_txt'=> zt('Write directory title on missing image'),
	'dir_img_txt_color'=> zt('Directory text color'),
	'dir_img_txt_wrap'=> zt('Wrap directory long text at this character'),
	'dir_img_txt_font'=> zt('Directory full path to TrueType font'),
	'dir_img_txt_font_size'=> zt('Directory font size'),
	'sub_img_txt'=> zt('Write sub-directory title on missing image'),
	'sub_img_txt_color'=> zt('Sub-directory text color'),
	'sub_img_txt_wrap'=> zt('Sub-directory wrap long text at this character'),
	'sub_img_txt_font'=> zt('Subdirectory full path to TrueType font'),
	'sub_img_txt_font_size'=> zt('Sub-directory font size'),
	'genre_img_txt'=> zt('Write genre title on missing image'),
	'genre_img_txt_color'=> zt('Genre text color'),
	'genre_img_txt_wrap'=> zt('Genre wrap long text at this character'),
	'genre_img_txt_font'=> zt('Genre full path to TrueType font'),
	'genre_img_txt_font_size'=> zt('Genre font size'),
	'search_img_txt'=> zt('Write search title on missing image'),
	'search_img_txt_color'=> zt('Search text color'),
	'search_img_txt_wrap'=> zt('Search wrap long text at this character'),
	'search_img_txt_font'=> zt('Search full path to TrueType font'),
	'search_img_txt_font_size'=> zt('Search font size'),
	'cmp_sel'=> zt('Download selected songs as compressed file'),
	'cmp_cache'=> zt('Cache compressed files'),
	'cmp_pgm'=> zt('Compression program (full path)'),
	'cmp_extension'=> zt('Compressed file extension'),
	'cmp_set'=> zt('Compression program options'),
	'cmp_mime'=> zt('Compressed file mime-type'),
	'pos'=> zt('Play on server'),
	'pos_hack'=> zt('mpg123 hack (try if using and having problems)'),
	'pos_cmd'=> zt('Play on server command'),
	'pos_kill'=> zt('Kill (necessary on linux/unix systems)'),
	'pos_kill_cmd'=> zt('Kill command'),
	'other_media_types'=> zt('Other media types'),
	'media_types_cfg'=> zt('Media types'),
	'cron'=> zt('Time based functionality'),
	'cron_opts'=> zt('Time based options'),
	'adm_pwd_old'=> zt('Old Password'),
	'adm_pwd'=> zt('New Password'),
	'adm_pwd_con'=> zt('Confirm Password'),
	'mm'=> zt('Look for Other Non-Music Media Types'),
	'mm_types_cfg'=> zt('Other Non-Music Media Types'),
	'mm_down'=> zt('Allow Download'),
	'db'=> zt('Your database is setup'),
	'db_type'=> zt('Database type'),
	'db_host'=> zt('Database hostname'),
	'db_name'=> zt('Database name'),
	'db_user'=> zt('Database user'),
	'db_pwd'=> zt('Database user password'),
	'db_pre'=> zt('Database table prefix'),
	'stats'=> zt('Gather statistics'),
	'stats_public'=> zt('Show a public statistics page'),
	'stats_images'=> zt('Show images on statistics pages'),
	'stats_rss'=> zt('RSS Statistics feeds'),
	'stats_limit'=> zt('Number of stats, e.g. Top X'),
	'stats_org'=> zt('Assume Artist/Title directory structure for stats'),
	'stats_to'=> zt('Minimum time between file plays by an IP address for recording stats'),
	'rating_dirs'=> zt('Let users rate directories'),
	'rating_dirs_public'=> zt('Show users ratings of directories within Zina'),
	'rating_files'=> zt('Let users rate files'),
	'rating_files_public'=> zt('Show users rating of files within Zina'),
	'rating_limit'=> zt('Minimum number of votes to appear in rating summaries'),
	'rss'=> zt('Generate rss feeds for directories with files in them'),
	'rss_podcast'=> zt('Make rss feed a podcast'),
	'rss_file'=> zt('Name of feed file'),
	'rss_mm'=> zt('Add multimedia files to feed'),
	'rating_random'=> zt('Show options to play random by ratings'),
	'rating_random_opts'=> zt('Options to show for play random by ratings'),
	'song_extras'=> zt('Enable extra features for song listings'),
	'song_extras_opt'=> zt('Extra definition(s)'),
	'cache_dir_abs'=> zt('Main Cache Directory'),
	'cache_dir_private_abs'=> zt('Private Cache Directory'),
	'cache_dir_public_rel'=> zt('Public Cache Directory'),
	'cache_tmpl'=> zt('Template Cache'),
	'cache_tmpl_expire'=> zt('Template Cache Life'),
	'cache_imgs'=> zt('Cache resized images'),
	'cache_imgs_dir_rel'=> zt('Image Cache Directory'),
	#'cache_tmpl_dir'=> zt('Template Cache Directory'),
	'cache_stats'=> zt('Use Stats caching'),
	'cache_stats_expire'=> zt('Stats Cache Life'),
	'lastfm'=>zt('Enable Last.fm Integration'),
	'lastfm_username'=>zt('Last.fm username'),
	'lastfm_password'=>zt('Last.fm password'),
	'twitter'=>zt('Enable twitter.com Integration'),
	'twitter_username'=>zt('Twitter username'),
	'twitter_password'=>zt('Twitter password'),
	'debug'=>zt('Debug'),
	'clean_urls'=>zt('Clean URLs'),
	'clean_urls_hack'=>zt('Clean URLs hack (for Windows)'),
	'clean_urls_index'=>zt('Clean URL hack "path" alias'),
	'sitemap'=> zt('Generate a sitemap'),
	'sitemap_cache'=> zt('Cache Sitemap file'),
	'sitemap_cache_expire'=> zt('Sitemap Cache life'),
	'session'=> zt('Allow the user to stay logged in'),
	'session_lifetime'=> zt('Stay logged in for'),
	'db_search'=> zt('Use database for search'),
	'search_structure'=> zt('Assume Artist/Album/Song directory structure'),
	'search_default'=> zt('Default behavior for a direct hit using "Live Search"'),
	'search_min_chars'=> zt('Minimum number of characters to allow search'),
	'search_pp'=> zt('Number of items per split page'),
	'search_pp_opts'=> zt('Search per page options'),
	'search_live_limit'=> zt('Number of items in "Live Search" drop down'),
	'search_images'=> zt('Show images in search results and live search'),
	'playlist_format'=> zt('Playlist format to return'),
	'zinamp'=> zt('Flash player'),
	'zinamp_skin'=> zt('Flash player skin'),
	'third_lyr'=> zt('Display lyrics from third party websites'),
	'third_lyr_order'=> zt('Search lyric sites in this order'),
	'third_lyr_save'=> zt('Save lyrics'),
	'third_images'=> zt('Show optional images from amazon.com, last.fm or google.com'),
	'third_addthis'=> zt('Display addthis.com "share/bookmark/feed" buttons'),
	'third_addthis_id'=> zt('addthis.com account id'),
	'third_addthis_options'=> zt('addthis.com services'),
	'third_amazon_private'=> zt('Amazon Web Service Secret Access Key'),
	'third_amazon_public'=> zt('Amazon Web Service Access Key ID'),
	'third_amazon_region'=> zt('Amazon Web Service region'),
	'locale'=> zt('Override PHP default locale'),
	'random_least_played'=> zt('Random play least played songs first'),
	'random_lp_floor'=> zt('Random play least played songs floor'),
	'random_lp_perc'=> zt('Random play least played songs percent'),
	'tags_cddb_auto_start'=> zt('Auto-start album match in tag editor'),
	'tags_keep_existing_data'=> zt('Keep tags that are present but not available from tag editor'),
	'tags_filemtime'=> zt('Attempt to keep file modification time unchanged when editing tags'),
	'tags_format'=> zt('Text encoding used for tag data'),
	'tags_cddb_server'=> zt('CDDB Server to use for tag lookup'),
);

$subs = array(
	'cms_editor'=> zt('Must give CMS users appropriate permissions via CMS, too. Drupal? See "Permissions->zina editor".  Joomla? Set user to Editor, Publisher, Manager, Administrator.  Wordpress? Set user to Editor.'),
	'stream_int'=> zt('Do not change this unless you know what you are doing.'),
	'genres_custom'=> zt('If true, you are responsible for deleting "empty" genres.').' '.zt('If false, Zina will attempt to delete genres with no file associations.'),
	'tags_format' => zt('e.g. ISO-8859-1, UTF-8, UTF-16, UTF-16LE, UTF-16BE (different tag formats only support certain encodings)'),
	'settings_override' => zt('Include a file in the directory named like the next setting.'),
	'settings_override_file'=> zt('File should include xml to overide settings, e.g. @xml', array('@xml'=>'<settings> <download>0</download> </settings>')).' '.
		zt('See "Time Base Functionality X=X:" for options.').' '.zt('(Not all make sense or will work.)'),
	'cat_various_lookahead' => zt('For Alphabetic options only. Performance hit.'),
	'apache_auth'=> zt('NOT for Flash player'),
	'random_least_played'=> zt('Requires a populated database and stats enabled'),
	'third_addthis_id'=> zt('Optional'),
	'mm_types_cfg'=> zt('[ext], mime = TYPE, disposition = inline|attachment, player = WMP, QT, USERDEFINED'),
	'third_lyr'=> zt('If available. "lyr" under "Music Files->Enable Extra Features..." must be enabled.'),
	'third_lyr_save'=> zt('Requires writeable filesystem or database.'),
	'third_images'=> zt('If available.  Admin only.'),
	'third_amazon_private' => zt('You will need a developer account from Amazon if you want album images from amazon.com.  See https://aws-portal.amazon.com/gp/aws/developer/registration/index.html'),
	'stream_extinf'=> zt('Where appropriate'),
	'charset'=> zt('Affects standalone version only.  CMSes set their own.  e.g. ISO-8859-1'),
	'locale'=> zt('e.g. en_US.UTF8, ru_RU.KOI8-R'),
	'db_search'=> zt('Requires a fully populated database.'),
	'zinamp'=> zt('Must set playlist format to "xspf".  Flash limits format to mp3s only.  Try Inline with SilverSmall skin or Pop-up with WinampClassic skin."'),
	'rating_random_opts'=> zt('>= list, comma separated'),
	'search_pp'=> zt('Default value if using database'),
	'search_pp_opts'=> zt('Comma separated list'),
	'rating_limit'=> zt('Comma separated list: ALL,YEAR,MONTH,WEEK,DAY)'),
	'image_captions'=> zt('Will look for text file with graphics filename with .txt added. e.g. cover.jpg.txt'),
	'stats'=> zt('Views, plays, downloads, etc.'),
	'low_lookahead'=> zt('Try only if resample is false, Lofi is true and not all directories have lofi files'),
	'dir_file'=>zt('Can reference internal urls via {internal:PATH} syntax'),
	'cache_stats'=> zt('Requires cron.php to be called regularly or manual regeneration above.'),
	'cache_stats_expire'=> zt('Requires cron.php to be called at least as frequently as this value.'),
	'cache_expire'=> zt('Requires cron.php to be run regularly.'),
	'song_extras'=> zt('Like lyrics/tabs/external link. Requires either manual file generation, writeable filesystem or database.'),
	'song_extras_opt'=> zt('Ini format.  [file extension] is the music filename with this extension.').' '.
		zt('By default, the extension is also used for the theme icon.').' '.
	 	zt('"type" can be page_internal, page_popup, or external.').' '.
	 	zt('page_popup needs page_width and page_height defined in pixels.').' '.
	 	zt('external means you will associate an external url with the file (not here though).')
		,
	'dir_list_sort'=> zt('Requires ID3'),
	'session'=> zt('Allow the user to stay logged in.'),
	'zina_dir_rel'=> zt('Relative path (only change if you are having problems)'),
	'play'=> zt('If you want to really deny access, your music files must be outside the webroot.'),
	'download'=> zt('If you want to really deny access, your music files must be outside the webroot.'),
	'cache'=> zt('For for custom playlists, faster random playlists, genres, etc.'),
	'cat_pp'=> zt('Should be a multiple of category columns.'),
	'cat_file'=> zt('Can include xml to overide settings per directory. e.g. @xml', array('@xml'=>'<category> <images>1</images> </category>')).' '.
		zt('Override options include images, columns, truncate, split [0,3,1,2], per_page, sort'),
	'cmp_cache'=> zt('Will take up lots of disk space, but less server processing.'),
	'cache_tmpl'=> zt('Caches main pages: Category/Artist/Albums for anonymous users.').' '.zt('WARNING: Do not enable until you have everything working!'),
	'cache_dir_abs'=> zt('Must be writeable by web server.'),
	'cache_dir_private_abs'=> zt('Must be writeable by web server.').' '.zt('Can be outside of webroot.'),
	'cache_dir_public_rel'=> zt('Relative path from zina file.').' '.zt('No trailing slashes.').' '.zt('Must be writeable by web server.'),
	'sitemap'=> zt('With clean urls, available at /sitemap.xml(.gz).  Otherwise, ?p=sitemap.xml'),
	'mp3_dir'=> zt('Full Path (no trailing slash).').' '.zt('Windows use forward slash, e.g. F:/music'),
	'toke_timeout'=> zt('Hours a token lasts'),
	'debug' => zt('Show warnings and more detailed error messages.'),
	'clean_urls' => zt('For prettier urls and allegedly better for search engines.  Requires Apache with mod_rewrite or ISS with ISAPI Rewrite.  If your are running Zina within a CMS, the CMS has to support clean urls and they should be turned on there.'),
	'clean_urls_hack' => zt('If your running Zina on Windows and your paths are being lowercased, use this.'),
	'clean_urls_index' => zt('A path alias.  Can NOT be an actual path below the zina script.'),
	'song_blurbs'=> zt('Named same as music file, but with .txt extention instead.').' '.
			zt('Can reference internal urls via {internal:PATH} syntax'),
	'various'=> zt('Requires ID3.  Great for Soundtracks/Compilations directories'),
	'enc_arr'=> zt('WARNING: using this will most likely bring your server to its knees.').' '.
			zt('You will need an external encoder like LAME [http://www.mp3dev.org/mp3/] which can take input from stdin and output to stdout. '),
	'resample'=>zt('Requires Low Fidelity setting above and an external program.'),
	'new_highlight'=> zt('Based on file modification time.  Might incur a performance penalty.'),
	'alt_dirs' =>
			zt('Directories selected below will appear in a "@section" section.',array('@section'=>zt('See Also'))),
	'remote' =>
			zt('Want to include a song on another server? Create a file in the directory named like the song title with the "Remote file extension", e.g. 02 - Remote Song.rem').' '.
			zt('XML File Format: @xml where tags can be: url, download, artist, album, title, filesize, bitrate, frequency, time, year, genre', array('@xml'=>'<file><tags></tags></file>')).' '.
			zt('Simplest example: @xml', array('@xml'=> '<file> <url>http://www.site.com/song.mp3</url> </file>')),
	'fake' =>
			zt('Want to create a placeholder for a song?  Create a file in the directory named like the song title with the "Placeholder file extension", e.g. 02 - Remote Song.rem'),
	'cache_imgs_dir_rel'=> zt('Relative path to zina file.  Must be writeable by the webserver and be accessible via http'),
);

$cats = array(
	'config' => array(
		't'=> zt('Configuration'),
		'd'=> ''
	),
	'cms' => array(
		't'=> zt('CMS Options'),
		'd'=> zt('Options for CMSes (Drupal, Joomla, Wordpress, etc.).'),
	),
	'integration' => array(
		't'=> zt('Last.fm / Twitter'),
		'd'=> zt('Send listening information to your last.fm and/or twitter accounts.').' '.zt('For logged in users only.').' '.zt('Requires "Private Key" in authentication.').' '.zt('Can be overriden by certain CMS settings.')
	),
	'third' => array(
		't'=> zt('Third Party Integration'),
		'd'=> ''
	),
	'general' => array(
		't'=> zt('Theme / Display'),
		'd'=> ''
	),
	'caches' => array(
		't'=> zt('Caches'),
		'd'=> array(
			zt('Zina uses various caches to speed things up.').' '.
				zt('Many features rely on the Main Cache, it is enabled by default.').' '.
				zt('Caches will be generated the first time they are needed (which might take awhile).').' '.
				zt('After that, you will need to have "cron.php" run regularly or regenerate them manually above.'),
			zt('You should get Zina running the way you want before enabling the other caches because they can make debugging difficult.').' '.
				zt('Also, the Template cache is not enabled when logged in.'),
		),
	),
	'db' => array(
		't'=> zt('Database'),
		'd'=> array(
			zt('Optional.  Required for statistics and optional features. You need MySQL 4.1 or greater.').' '.
				zt('You will need to already have set up a database and have a username and password for that database.'),
			zt('If this section is greyed out, Zina is using your CMSes database')
		),
	),
	'dirs' => array(
		't'=> zt('Directories'),
		'd'=> ''
	),
	'categories' => array(
		't'=> zt('Directory as Category'),
		'd'=> zt('List directories in a column format.  Good for main directory or any directory with lots of sub-directories.')
	),
	'files' => array(
		't'=> zt('Music Files'),
		'd'=> ''
	),
	'tags' => array(
		't'=> zt('Tag Editor'),
		'd'=> zt('Your filesystem must be writeable.')
	),
	'search' => array(
		't'=> zt('Search'),
		'd'=> '',
	),
	'various' => array(
		't'=> zt('"Artist - Title" Song Titles'),
		'd'=> zt('Makes songs in a directory display "Artist - Title" song titles (requires ID3).  Good for soundtracks and various artist directories.')
	),
	'mm' => array(
		't'=> zt('Non-Music Media'),
		'd'=> array(
			zt('List and/or allow download of non-music media types.  Types should have a graphic file in THEMES/images/mm.'),
			zt('If using song blurbs, you can have blurb file with the same name as the file with a .txt extension added.'),
			zt('EXPERIMENTAL: If running Zina locally, "p" allows file to be played embedded in Zina via WMP or QT or add a selection to THEMES/templates-video.html.')
		),
	),
	'advanced' => array(
		't'=> zt('Advanced'),
		'd'=> zt('It should not be necessary to change these settings.').' '.zt('Only change if you are having problems or have specific needs.'),
	),
	'auth' => array(
		't'=> zt('Authentication'),
		'd'=> ''
	),
	'stats' => array(
		't'=> zt('Statistics'),
		'd'=> zt('Requires your database to be setup above.'),
	),
	'images' => array(
		't'=> zt('Dynamic Image Resizing'),
		'd'=> zt('Needs PHP compiled with GD library.').' '.
			zt('Zina should detect your configuration.'),
	),
	'podcasts' => array(
		't'=> zt('Podcasts and Sitemap'),
		'd'=>'',
	),
	'genres' => array(
		't'=> zt('Genres'),
		'd'=> array(
			zt('Requires ID3 setting, and Main Cache or Database.'),
			zt('After enabling, cache will be generated.').' '.
			zt('You can also generate cache manually above.'),
			zt('If you use genre images, you can have custom genre images by placing them in your theme "images" folder with names "genre_SOMEGENRE.jpg".').' '.
			zt('Default filename/type can be changed by overriding the theme function.')
		),
	),
	'compress' => array(
		't'=> zt('Download Compressed Files'),
		'd'=> zt('Allow users to download multiple files in a compressed format like .zip.').' '.
			zt('Requires an external cmd line compression program like zip or gzip, or php zip module.').' '.
			zt('Will put a strain on your server.'),
	),
	'pos' => array(
		't'=> zt('Play on Server'),
		'd'=> array(
			zt('Play songs on the server Zina runs on as opposed to playing on the browser\'s machine.'),
			zt('<b>Windows/WinAmp</b> (You will most likely need http://www.jukkis.net/bgrun/bgrun.exe)').'<br/>'.
				zt('Command example').': <code>bgrun.exe C:\Progra~1\Winamp\winamp.exe %TEMPFILENAME%.m3u >NUL</code>',
			zt('<b>Linux/mpg123</b><br/>').
				zt('Command example').': <code>/usr/bin/mpg123 -q --list %TEMPFILENAME%.m3u 0 1> /dev/null 2>&1 &</code>',
			zt('<b>OS/2</b> and <b>eCS</b> try using Z! http://dink.org/z/').'<br/>'.
				zt('Command example').': <code>z.exe -p %TEMPFILENAME%.m3u >NUL</code>'
			),
		),
	'other_media' => array(
		't'=> zt('Media Types'),
		'd'=> array(
			zt('Default setting: mp3,ogg,wav,wma using .m3u playlists via http protocol.').' '.
				zt('To enable other media types, set "Other Media Types" to true and add a new section.').' '.
				zt('Having different files in the same directory that utilize different playlist types will not work.').' '.
				zt('Note: Your server has to support these other media types (e.g. Real Audio, etc.).'),
			zt('An example').':<br/><code>[ra]<br/>mime = audio/vnd.rn-realaudio<br/>'.
				'protocol = rtsp<br/>playlist_ext = ram<br/>playlist_mime = audio/x-pn-realaudio</code>',
			),
		),
	'cron' => array(
		't'=> zt('Time Based Functionality'),
		'd'=> array(
			zt('Any feature that is set "true or false" can be optionally set to work during certain time periods using "cron"-like syntax for anonymous users.'),
			zt('Uses "ini" style syntax. See drop down for settings').'<br/>'.
			'<code>X = TIME</code><br/>'.
				zt('X = %CRON_SELECT%').'<br/>'.
				'<code>TIME = WDAY MON MDAY HH:MM (where...WDAY=0-6, with 0=Sunday; MON=1-12; MDAY=1-31; HH:MM=0-23:0-59)</code><br/>'.
				zt('"*" matches all; use comma between multiple values; a hyphen to indicate range'),
			zt('Example 1: Would allow playing all the time (totally pointless)').'<br/>'.
				'<code>play[] = * * * *</code><br/>'.
			zt('Example 2: Would allow downloading between 10pm and 6am everyday...').'<br/>'.
				'<code>download[] = * * * 22:00-6:00</code><br/>'.
			zt('Example 3: Would allow downloading Monday thru Friday during Jan, Mar, May for the first 15 days between 8am and 5pm)').'<br/>'.
				'<code>download[] = 1-5 1,3,5 1-15 8:00-17:00</code><br/>'.
			zt('Example 4: Would allow downloading between Midnight and 6am M-F and all day Sat & Sun').'<br/>'.
				'<code>download[] = * * * 0:00-6:00<br/>download[] = 6-0 * * *</code>',
			),
		),
);
?>
