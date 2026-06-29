=== Nero AI Product Image Optimizer for WooCommerce ===
Contributors: neroai
Tags: woocommerce, background removal, background changer, product image editing, ai image editing
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WooCommerce product image optimizer that removes or changes backgrounds in bulk. Create clean, professional product photos with one click.

== Description ==

Nero AI Product Image Optimizer for WooCommerce helps merchants instantly improve product photos using Nero AI. It provides two main tools:

= Background Removal =
- Remove backgrounds from product images with one click
- Ideal for clean, consistent catalog photos

= Background Change =
- Change the background to a solid color, gradient, or a custom image
- Upload your own background image or pick from presets


= Key Features =
- Batch processing to remove or change backgrounds for multiple images, with per-image progress tracking
- Smart subject detection for accurate background removal, with fine detail handling
- Ideal for e-commerce product photos, marketing creatives, and social media visuals
- Free to start with an API key and 50 free credits for image processing
- Supports JPG, JPEG, JPE, PNG, BMP, and WEBP formats
- Modern, user-friendly interface with tabs for Remove BG / Change BG
- Media Library integration for direct image background editing
- Accessibility and responsive layout
- Credits-aware UI with per-item badges and banner


== Installation ==

From your WordPress dashboard
1. Navigate to Plugins > Add New
2. Search for ‘Nero AI Product Image Optimizer’ and click Install Now to install the plugin by Nero AI
3. Once installed, activate the plugin and enter your API key from [Nero AI Official Site](https://ai.nero.com/ai-api)
5. Choose the image you want to process and click **Start Bulk Processing**

From WordPress.org
1. Visit https://wordpress.org/plugins and search for ‘Nero AI Product Image Opimizer’
2. Download the plugin developed by Nero AI
3. In your WordPress dashboard, go to Plugins > Add New > Upload Plugin and upload the .zip file
4. Activate the plugin, open WooCommerce > Nero AI Product Image Optimizer, and paste your API key from [Nero AI Official Site](https://ai.nero.com/ai-api)
5. Select your desired image and begin optimizing them in bulk


== Getting started ==
Install this plugin and create your Nero AI account at [Nero AI Official Site](https://ai.nero.com/ai-api) to obtain your API key. New users can claim 50 free credits (equals up to 50 free images) on the Nero AI API page to try processing images at no cost.

After activation, enter your API key in WooCommerce → Nero AI Product Image Optimizer and save. You can then select images from your Media Library or upload new ones, select Remove Backgrounds or Change Backgrounds, and start batch processing.

Progress and results of processing will be displayed in a clear list. You can download all of them or replace the original images with the processed images.



== Screenshots ==

1. Enter your Nero AI API key
2. Select images from the Media Library
3. Remove Background tab and controls
4. Change Background tab and add new backgrounds.
5. Bulk processing with per-item status
6. Result actions: Replace All / Download All



== Frequently Asked Questions ==

= Where can I get my API Key? =
To begin, register an account at the [Nero AI Official Site](https://ai.nero.com/ai-api) and get your API Key. Once obtained, enter this key in the plugin API input box.  
After obtaining the API Key, go to the [Nero AI API Status Board](https://ai.nero.com/ai-api/status) page and click **Claim 50 Credits** to receive your free trial credits.

= Do I need to pay before using the service? = 
  No. You can start for free — after registering, you will instantly receive **50 free credits** to try out image background removal and replacement.

= What image formats are supported? =
JPG, JPEG, JPE, PNG, BMP, and WEBP.

= How many images can I process for free? =
In general, you receive **50 free credits** after claiming them in the API Status page.  
- Background removal: 1 image = 1 credit → up to 50 images  
- Background replacement with solid color: 1 image = 1 credit → up to 50 images  
- Background replacement with another image: 1 image = 2 credits → up to 25 images

= How do Credits work? =
- Remove BG: 1 credit per image
- Change BG with color/gradient: 1 credit per image
- Change BG with background image: 2 credits per image


= Will my original images be overwritten? =
Your originals remain until you explicitly choose "Replace All" after processing. You can also use "Download All" to save processed images as new files in a dedicated folder without altering originals.

= Where are downloaded files stored? =
Processed files are saved in the same directory as the original image, using a suffix "-nero-ai-bg-removed" or "-nero-ai-bg-changed".

= Why does processing fail sometimes? =
Common reasons include invalid/expired API key, insufficient credits, or temporary network issues. The UI will show error toasts with details.


== Contact us ==
If you have any questions, suggestions, or feedback, feel free to reach out at feedback@nero.com.

== External services ==

This plugin connects to the Nero AI API to remove/change image backgrounds. The service is required for core functionality.

- Service Provider: Nero AI (https://ai.nero.com)
- Purpose: AI-powered product image background removal/change
- Data Sent:
  - Selected product images from your Media Library
  - API authentication key
  - Background parameters (solid color, gradient definition, or referenced background image URL when applicable)
- When Data Is Sent:
  - During manual bulk processing when you click "Start Bulk Processing"
- Data Processing: Images are processed on Nero AI's secure servers. Processed results are returned to your site. Nero AI does not permanently store your images.

Important links:
- Service Website: https://ai.nero.com
- Terms of Service: https://ai.nero.com/terms
- Privacy Policy: https://www.nero.com/eng/corp-legal/privacy.php
- API Documentation: https://ai.nero.com/ai-api/docs

== Compatibility ==
- Compatible with WordPress 5.6+
- Designed for WooCommerce stores

== Changelog ==

= 1.0.0 =
* Initial release.
