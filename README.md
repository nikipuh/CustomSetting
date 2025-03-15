# CustomSetting Plugin
CustomSetting is a PocketMine-MP plugin that provides a persistent custom form for server settings, allowing you to create a customizable information page for your Minecraft Bedrock server.
![image](https://i.imgur.com/cEkzx5h.png)

## Features
- Settings form for all players
- Customizable form content via `form.json`
- Rich text formatting support

### How It Works:
1. **Player Opens Settings**:
   - The player accesses the settings menu on their device.

2. **Plugin Detects and Sends Forms**:
   - The plugin detects when the player opens the settings and immediately sends a series of **5 forms** (pre-configured settings pages) to the player.
   - This ensures the player receives the forms, even if the initial form was sent before they opened the settings.

3. **Handling Early Closure**:
   - If the player closes the settings before all 5 forms are sent, the plugin detects this and **automatically stops sending forms**.

### Key Points:
- **Guaranteed Delivery**: Sending multiple forms ensures the player receives the settings, even if they experience a delay in opening the settings menu.
- **Efficient Handling**: The plugin stops sending forms if the player closes the settings early, avoiding unnecessary actions.
- **Fast Detection**: The plugin actively monitors the player's actions to ensure timely and efficient form delivery.

## Installation
1. Download the plugin
2. Place in your server's `plugins/` directory
3. Restart the server

## Form Customization

### Text Formatting Codes

#### Color Codes
| Code | Color | Code | Color |
|------|-------|------|-------|
| `§0` | Black | `§1` | Dark Blue |
| `§2` | Dark Green | `§3` | Dark Aqua |
| `§4` | Dark Red | `§5` | Dark Purple |
| `§6` | Gold | `§7` | Gray |
| `§8` | Dark Gray | `§9` | Blue |
| `§a` | Green | `§b` | Aqua |
| `§c` | Red | `§d` | Light Purple |
| `§e` | Yellow | `§f` | White |

#### Formatting Styles
| Code | Effect | 
|------|--------|
| `§l` | Bold |
| `§o` | Italic |
| `§k` | Obfuscated (random characters) |
| `§r` | Reset formatting |

#### Combining Formatting
You can combine multiple formatting codes for creative text styling:
- `§l§9Bold Blue Text`
- `§c§oRed Italic Text`
- `§6Gold Text`

## Icon Configuration

### Icon Types
The plugin supports three ways to set the form icon:

1. **Minecraft Default Textures**
```json
"icon": {
  "type": "path",
  "data": "textures/items/cookie"
}
```
Some popular texture paths:
- `textures/items/cookie`
- `textures/items/diamond`
- `textures/items/book_written`
- `textures/items/paper`
- `textures/items/compass`

2. **Base64 Encoded Image**
```json
"icon": {
  "type": "url",
  "data": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUg..."
}
```
To create a base64 icon:
- Use an image editing tool
- Resize to 64x64 pixels
- Convert to PNG
- Use an online base64 encoder

3. **URL Image** (not recommended due to potential loading issues)
```json
"icon": {
  "type": "url",
  "data": "https://example.com/icon.png"
}
```

## Example form.json
```json
{
  "type": "custom_form",
  "title": "§eServer Info",
  "icon": {
    "type": "path",
    "data": "textures/items/diamond"
  },
  "content": [
    {
      "type": "label",
      "text": "§l§6Welcome to Our Server!§r"
    },
    {
      "type": "label",
      "text": "§bRules:§r\n§a1.§f Be respectful\n§a2.§f No griefing"
    }
  ]
}
```

## Configuration Location
The plugin configuration is stored at:
`plugin_data/CustomSetting/form.json`

## Troubleshooting
- Ensure `form.json` is valid JSON
- Check server logs for any error messages
- Verify file permissions
- The plugin will generate a default form if `form.json` is missing or invalid

## Contributing
Contributions are welcome! Open an issue or submit a pull request.

## License
This project is licensed under https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode 

## Credits
Created by nikipuh for the PocketMine-MP community <3

