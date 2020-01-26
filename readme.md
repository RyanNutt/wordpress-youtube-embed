# YouTube Embed Extras

This small plugin adds meta data to your WordPress posts and pages that contain an embedded YouTube video. When it finds one it queries the YouTube API for information to add to the meta sections of your page. It also downloads the largest thumbnail it can find from YouTube thanks to the [KM_Download_Remote_Image class from Kellen Mace](https://kellenmace.com/download-insert-remote-image-file-wordpress-media-library/). 

It also piggy backs on the outstanding [Yoast SEO plugin](https://yoast.com/wordpress/plugins/seo/) to add extra meta data to those posts and page with YouTube videos. 

> One note. This only looks for the first embedded YouTube video, so if your post has multiple videos it will only look at the first for thumbnails and meta data. 

## Installation

Grab the [latest release zip file](https://github.com/RyanNutt/wordpress-youtube-embed/releases) from GitHub.

Login to your WordPress site. Click on Plugins > Add New and then the Upload Plugin button.

Upload the zip file and activate. 

Once you've entered your YouTube API key (the next step) the plugin will start working. 

## Settings

There's only one. You'll need to enter a YouTube API key in the Settings > Media page.

If you don't have an API key, you can get one from Google. They're free unless you're doing a lot of API calls, and this plugin only needs to do 1 or 2 per post when it's first viewed. After that the data is saved as part of the post. 