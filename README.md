# Egg Changer for Pterodactyl

[![Release](https://img.shields.io/github/v/release/CCBlueX/Pterodactyl-Extension-Egg-Changer?style=flat-square)](https://github.com/CCBlueX/Pterodactyl-Extension-Egg-Changer/releases/latest)
[![Downloads](https://img.shields.io/github/downloads/CCBlueX/Pterodactyl-Extension-Egg-Changer/total?style=flat-square)](https://github.com/CCBlueX/Pterodactyl-Extension-Egg-Changer/releases)
[![Pterodactyl](https://img.shields.io/badge/Pterodactyl-2.0-0e4688?style=flat-square)](https://pterodactyl.io)
[![License](https://img.shields.io/github/license/CCBlueX/Pterodactyl-Extension-Egg-Changer?style=flat-square)](LICENSE)

Let users switch their server to another egg you allow, right from the Startup page.

![Egg switcher](docs/screenshots/startup.png)

## Install

1. Download `egg-changer-<version>.pteroext` from the [latest release](https://github.com/CCBlueX/Pterodactyl-Extension-Egg-Changer/releases/latest).
2. Open **Admin → Extensions**, click **Install** and pick the file.
3. Enable it, then create a group under **Admin → Egg Changer**.

## Features

- **Groups**: a server can switch to any egg that shares a group with its current egg, like Paper, Fabric and Forge.
- **Keep variables**: values the new egg still accepts stay; the rest goes back to defaults.
- **Reinstall**: run the new egg's install script right away, if the user may reinstall.
- **Port ranges**: give an egg ports such as `5520-5600`; a server switching to it moves to a free port there.
- **Safe**: only works while the server is stopped, and shows up in the activity log.

![Groups](docs/screenshots/groups.png)

## Permissions

Subusers need **Egg changer** in the server's **Users** tab. Reinstalling also needs the core reinstall permission.

---

Inspired by the Pelican plugin Egg Selector.
