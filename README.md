# Style Manager

This plugin is the engine behind the Style Manager platform aimed at fulfilling the following needs:
- Managing various design assets (Color Palettes, Font Palettes, Layouts, Theme Configs, etc)
- Provide API endpoints for offering the various design assets to individual WordPress installations
- Provide API endpoints for saving user-created design assets
- Integrate with the Pixelgrade shop for customer and site consistent identification
- Gather usage data and display it for analysis

From the plugin's settings page (`Settings > Style Manager`) you can set various platform options.

## The API Endpoints

### Get Design Assets

```
http://somedomain.com/wp-json/cloud/v1/front/design_assets
```

