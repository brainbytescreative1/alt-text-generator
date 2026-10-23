# Brain Bytes AI Alt Text

A standalone WordPress prototype that adds a **Generate alt text** button directly below the alt-text field in the Media Library and the media popup used when adding images to a page.

Click the button, review or edit the suggestion, then choose **Use alt text** to save it. Suggestions use the OpenAI Responses API. Nothing is generated automatically on upload.

## Install

Clone this repository into a folder named `alt-text-generator` inside `wp-content/plugins`, or download the repository ZIP and upload it through **Plugins > Add New > Upload Plugin**.

1. Activate **Brain Bytes AI Alt Text (Prototype)**.
2. Open **Settings > AI Alt Text** and save your OpenAI API key.
3. Open an image in the Media Library, or upload/select an image while editing a page.
4. Click **Generate alt text**, review the suggestion, and click **Use alt text**.

Requires WordPress 6.4+, PHP 7.4+, a working WordPress image editor (GD or Imagick), and an OpenAI API project with billing and access to the selected model. This plugin is independent of the CSV-based Media Auto Alt Text Creation plugin.

## API key

The settings field stores the key in a non-autoloaded WordPress database option. It is not encrypted at rest, and it is never rendered back to the browser. Alternatively, configure a server environment variable named `BBAIAT_OPENAI_API_KEY` or define that constant in `wp-config.php`. Server configuration takes precedence over the saved setting.

Never commit an API key to this repository. No key is included in this prototype.

## Prototype scope

- One image at a time; JPEG, PNG, and WebP stored locally inside the uploads directory.
- Sends a temporary resized image copy, up to 1024 pixels on its longest side, to OpenAI only when Generate is clicked. API charges may apply.
- Saves only the attachment's alt text; filenames, titles, captions, and descriptions stay unchanged.
- Updates the current media popup's attachment model so the selected image uses the saved alt text.
- Existing page blocks and page builders may store their own alt text; update those instances separately.
- No bulk queue, automatic upload generation, cloud-only media, SVG, GIF, or AVIF support.
- Administrators manage settings. Generation requires permission to upload files and edit the image.

Review descriptions for accuracy and the image's purpose in its page context. Decorative images may need empty alt text.

## Validation

Version 0.1.0 passed PHP and JavaScript syntax checks, 35 integration checks against a local WordPress installation, and browser checks for Media Library list/grid, Edit Media, page-editor media selection, new uploads, settings, and saving. API responses were mocked; live generation still requires testing with your API key. The minimum supported WordPress and PHP versions were not separately exercised.

See [readme.txt](readme.txt) for service disclosures, key storage, and additional limitations.

License: GPL-2.0-or-later.
