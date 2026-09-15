Drop your exported ACF field group JSON here.

On the OLD site: ACF → Field Groups → (your product group) → ... → Export →
"Export As JSON" → save the .json file → place it in this folder.

ACF on the new site will auto-load it, so the product fields (sub-name, colour
variants, paragraphs, downloads, specification, gallery, etc.) appear in the
editor and get_field() resolves correctly. No code changes needed.
