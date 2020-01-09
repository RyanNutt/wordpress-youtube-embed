# WPSEO YouTube Embeds

This tiny WordPress plugin piggy backs on the outstanding [Yoast SEO plugin](https://yoast.com/wordpress/plugins/seo/) to add meta data to your posts that have embedded YouTube videos.

What it does is look for embedded YouTube videos. When one is found it queries the YouTube API and gets information for JSON-LD and Open Graph tags and adds them to your page. 

## Installation

Grab the [latest release zip file](https://github.com/RyanNutt/wordpress-wpseo-og-video/releases) from GitHub.

Login to your WordPress site. Click on Plugins > Add New and then the Upload Plugin button.

Upload the zip file and activate. 

Once you've entered your YouTube API key (the next step) the plugin will start working. 

## Settings

There's only one. You'll need to enter a YouTube API key in the Settings > Media page.

If you don't have an API key, you can get one from Google. They're free unless you're doing a lot of API calls, and this plugin only needs to do 1 or 2 per post when it's first viewed. After that the data is saved as part of the post. 