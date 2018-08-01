# Kerbal Science Recap

## Introduction

This program allows you to see your science progress in a more friendly way than the game proposes. It uses the [wiki's science format](https://wiki.kerbalspaceprogram.com/wiki/Science#Possible_combinations_of_Activity.2C_Situation.2C_and_Biome) which displays all science for each biome and shows only discovered science and bodies

![Visual demo](http://vrac.goufastyle.org/pr/demo/kerbalscience/kerbalscience.png)

## Installation

Kerbal Science Recap is a PHP website with a tiny bit of Javascript. It can run in download mode (provide a persistent.sfs file through a form) or permanent mode (open a file on your filesystem).
If you want to give it a try, go to [the demo website]( http://vrac.goufastyle.org/pr/demo/kerbalscience/kerbalscience.php)
Just clone the repo in your website's filesystem, launch [composer]( https://getcomposer.org/), adjust the config.inc.php file and serve it.

## Configuration
### config.inc.php
It contains the website's general configuration
- **SAVEFILE** : location of your SFS savefile, empty if you want to use download mode
- **LOCALE** : the locale of the translation

### bodies.json
This file contains some metadata about celestial bodies and science behaviour depending on specific situations
- **situations** : list of all situations and their forbidden experiments
- **translateBiomes** : on the fly associative array to stack found translated biomes in the form of `translateBiomes[biomeBody]=translatedBiome`
- **translationSeparator** : locale oriented regex containing a word separator
- **foundScienceType** : on the fly list of strings to tell which science type (mystery goo, temperature,...) has been found. If you want your science type to be sorted, you can use this table
- **foundBodies** : on the fly list of strings to tell which body has been found. If you want your bodies to be sorted, you can use this table but beware of empty pages !
- **recoveries** : list of recovery types, to differentiate with standard science types
- **bodies** : bodies can be deduced by science blocks but some information (atmosphere altitude, landable, no water) is inaccessible in the persistent file. Therefore they are all listed here.

The bodies objects : 
- **name** : string identifier of the body
- **translation** : locale oriented name of the body to help translation
- **atmosphere** : boolean to tell if the body can do atmosphere science experiments
- **water** : boolean to tell if the body can do splashed science experiments
- **landable** : boolean to tell if the body can do landed science experiments
- **atmosphereLimit** : lower and upper limit of the atmosphere to tell the flying limits of science experiments
- **spaceLimit** : low space limit for space expermiments
- **biomes** : on the fly list of strings to tell which biome has been found. If you want your biomes to be sorted, you can use this table.
- [**filtername**] : display name of the filter if you want to restrain biomes
- [**showBiomes**] : list of biomes to restrain
- [**hideBiomes**] : list of biomes to hide

## Legend and navigation

The result HTML page consists of these elements :
- the body navigation bar
- recovery per body
- science per body and biome
- asteroids per body

The science per biome has the following color code
- **White** : science not done
- **Red** : impossible science, for example sismic scan while flying
- **Yellow** : global science
- **Green** : biome specific science

A fraction means that some science points can still be gathered if the experience is done again. `0 !!` means somebody/something did the experiment but didn't send it or it hasn't been retrieved, so beware not to lose it!

## Database

The script opens the savefile and looks for science blocks which have this format
```
Science
{
    id = sciencetype@BodySituation[Biome][_Asteroid]
    title = translated science recovery
    sci = current science points
    cap = maximum science points
}
```
A complete PHP table object is generated with every line in the form of
```json
{
    "body":"Kerbin",
    "where":"Global",
    "what":"mysteryGoo",
    "many":11.7,
    "how":"FlyingHigh",
    "asteroid":"",
    "retrieved":9
}
```
Then accessed through YaLinqo queries.

## Biome filtering

Kerbin has a lot of [exotic biomes](https://i.imgur.com/lKWuTu9.png), so I made a functionnality to show/hide biomes. This is done by adding fields in the bodies.json file and duplicating bodies.

## Translation

**Warning : translation of biomes is done AS-IS and is not reliable**
The only way to have a biome translation is in the Science bloc in the persistent savefile :
```
id = mobileMaterialsLab@KerbinSrfLandedRunway
title = Étude de matériaux de Piste de décollage
```
or
```
id = crewReport@KerbinFlyingLowGrasslands
title = Rapport d'équipage au-dessus de Prairies de Kerbin
```
In french, we have the following pattern :
```
[experience] de [situation] de [biome]_[asteroid] de [body]
```
Several problems occur...
* The [body] goes away when exotic KSC biomes are mentionned
* Mun is (for the moment) the only body translated to "La Mune" in french, therefore an additional display name for each body is required
* Some biomes can have "de" in their names
* In french we also have apostrophes : de Kerbin => d'Eeloo and the title string can mix ’ and '

Of course the solution could be to list all the biomes. but the script is supposed to be self learning, to encourage exploration, and avoid this writing task...

## Asteroids

Asteroids have a display name (ISL-245) and an internal name (_Potatoid2019). The matching is in another file.
Science on asteroids is done per biome, situation and body, which makes it difficult to sort with existing biomes. The choice made was to include asteroids at the bottom of the biome list since for the moment there is only one type of experience per asteroid
Of course you can fork the code to display data on your own using the first table generated.

## JSON export
You can export all the science found in your savefile in this format by clicking on the "Toggle JSON" button.