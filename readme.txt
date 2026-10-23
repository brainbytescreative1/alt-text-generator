=== Brain Bytes AI Alt Text (Prototype) ===
Contributors: brainbytescreative
Tags: accessibility, alt text, media, openai
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate image alt text with a button in WordPress. Review the suggestion before saving.

== Installation ==

1. Upload bb-ai-alt-text-0.1.0.zip through Plugins > Add New > Upload Plugin.
2. Activate Brain Bytes AI Alt Text (Prototype).
3. Open Settings > AI Alt Text, enter your OpenAI API key, and save.
4. Open an image in the Media Library or the media popup while adding an image to a page.
5. Click Generate alt text, edit the suggestion if needed, then click Use alt text.

The button also appears on the full Edit Media screen and in Media Library list view.
The image must finish uploading before its button becomes available.

== External service ==

This plugin calls https://api.openai.com/v1/responses only when an authorized user clicks Generate alt text.
It sends a resized image copy (up to 1024 x 1024 pixels, aspect ratio preserved), a description prompt,
the selected model, and your chosen output language. It does not send the filename, page text, or image URL.
Requests set store=false; this is not a promise of zero data retention by OpenAI.
OpenAI API billing and model access are required. API charges may apply each time you generate.

Terms: https://openai.com/policies/terms-of-use/
Privacy: https://openai.com/policies/privacy-policy/
API data controls: https://developers.openai.com/api/docs/guides/your-data

== Key storage ==

The settings field stores a key in a non-autoloaded WordPress database option.
It is not encrypted at rest. The key is never rendered back to the browser or written to plugin logs.
For server-managed secrets, set BBAIAT_OPENAI_API_KEY as an environment variable, or add this to wp-config.php:

define( 'BBAIAT_OPENAI_API_KEY', 'your-key-here' );

Do not add your real key to plugin source control. A configured constant/environment takes precedence over the saved setting.
Deleting the plugin removes its saved key and settings; generated alt text remains.

== Scope and limitations ==

This is an independent prototype; the CSV plugin is not required. Both can coexist, but either plugin's
manual generation can replace alt text previously saved by the other. Existing upload automation in
the CSV plugin remains active if that plugin is enabled.

Only single-image generation is included. There is no bulk queue or automatic generation on upload.
JPEG, PNG, and WebP must be locally readable inside this site's uploads folder.
WordPress needs GD or Imagick support for that image type. SVG, GIF, AVIF, cloud-only media,
and files stored outside the uploads folder are not supported in this prototype.

Suggestions are not saved until Use alt text is clicked. Dismiss leaves existing alt text unchanged.
Use alt text updates only _wp_attachment_image_alt and the current media popup's attachment model.
You can intentionally clear a suggestion before saving to use empty alt text.
Titles, captions, descriptions, files, and URLs are unchanged.
Existing Image blocks/page builders may store their own alt text. Update those instances separately.

AI can make mistakes. Review descriptions for accuracy and the purpose of the image on the page.
Decorative images may need an empty alt attribute. The plugin does not infer page context.
Users need both upload_files and edit_post permission for the image. Administrators manage API settings.
Nonces protect both AJAX endpoints, and a per-image lock prevents duplicate simultaneous API calls.
A changed saved alt value causes a conflict response rather than overwriting a newer edit.

== Development references ==

https://developers.openai.com/api/docs/guides/images-vision
https://developers.openai.com/api/docs/models/gpt-5.4-mini
https://developer.wordpress.org/reference/hooks/attachment_fields_to_edit/
