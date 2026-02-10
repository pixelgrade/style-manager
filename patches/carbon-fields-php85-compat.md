# Carbon Fields PHP 8.5 Compatibility Patches

Applied to `vendor/htmlburger/carbon-fields/core/` (v3.6.9).

These patches fix PHP 8.1+ deprecation: passing null to non-nullable internal function parameters.
Re-apply after updating Carbon Fields.

## Files Modified

### Helper/Helper.php (8 locations)
- `normalize_label()`: Cast `$label` to string at top; coalesce `preg_replace()` results with `?? $label`
- `normalize_type()`: Cast `$type` to string at top; coalesce `preg_replace()` results with `?? $normalized_type`
- `get_relation_type_from_array()`: Cast `$array[$relation_key]` in `strtoupper()` call
- `class_to_type()`: Coalesce `preg_replace()` result with `?? $type`
- `sanitize_classes()`: Cast `$classes` to string in `explode()` call
- `get_attachment_id()`: Cast `apply_filters()` result to string
- `get_attachment_metadata()`: Cast `$attachment_metadata['filetype']['type']` to string in `preg_replace()`
- `get_active_sidebars()`: Cast `$sidebar['class']` to string in `strpos()` call

### Container/Repository.php (4 locations)
- `get_unique_container_id()`: Cast `$title` to string at top; coalesce `preg_replace()` chain; cast `$title` in `md5()`

### Field/Field.php (1 location)
- `set_attribute()`: Cast `$name` to string at top; coalesce `preg_replace()` chain

### Field/Association_Field.php (4 locations)
- `value_string_to_property_array()`: Cast `$value_string` in `explode()` call
- `load()`: Cast `$value_string` in `trim()` call
- SQL `preg_replace` on options query: Coalesce result
- `get_title_by_type()`: Cast `$title` in `strlen()`/`substr()` calls

### Container/Theme_Options_Container.php (2 locations)
- `title_to_filename()`: Cast `$title` to string at top; coalesce `preg_replace()` result

### Container/Container.php (1 location)
- `get_field_by_name()`: Cast `$field_name` in `explode()` call

### Helper/Color.php (1 location)
- `hex_to_rgba()`: Cast `$hex` to string at top

### Helper/Delimiter.php (3 locations)
- `quote()`: Cast `$value` to string in `str_replace()` call
- `unquote()`: Cast `$value` to string in `str_replace()` call
- `split()`: Cast `$value` to string in `preg_split()` call

### Service/Meta_Query_Service.php (1 location)
- `filter_meta_query_array()`: Cast `$condition['key']` to string in `preg_replace()` call
