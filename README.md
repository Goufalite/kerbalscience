You can find here several external tools to help parsing the savefile of a KSP game and detail some information:
- [science found on planets in a more friendly way](#Kerbal-Science)
- [a tourist voyage planner](#tourist-planner)
- [advancement of experience of kerbals](#kerbal-xp)

# Introduction

## Installation

All programs are in the form of a PHP webpage with a tiny bit of Javascript. They can run in download mode (provide a persistent.sfs file through a form) or permanent mode (open a file on your filesystem).

Just clone the repo in your website's filesystem, launch [composer]( https://getcomposer.org/), adjust the config.inc.php file and serve it.

## Configuration

### config.inc.php
It contains the website's general configuration
- **SAVEFILE** : location of your SFS savefile, empty if you want to use download mode
- **STEAMPATH** : location of your KSP installation saves folder. You can then change your savefile by using the GET parameter "save" (for example save=mysavefile)
- **LOCALE** : the locale of the translation

### bodies.json
This file contains some metadata about celestial bodies and science behaviour depending on specific situations
- **situations** : list of all situations and their forbidden experiments
- **specificScience** : list of airless and atmospheric science experiments
- **recoveries** : list of recovery types, to differentiate with standard science types
- **foundScienceType** : on the fly list of strings to tell which science type (mystery goo, temperature,...) has been found. If you want your science type to be sorted, you can use this table
- **foundBodies** : on the fly list of strings to tell which body has been found. If you want your bodies to be sorted, you can use this table but beware of empty pages !
- **translationSeparator** : locale oriented regex containing a word separator
- **translateBiomes** : on the fly associative array to stack found translated biomes in the form of `translateBiomes[biomeBody]=translatedBiome`
- **bodies** : bodies can be deduced by science blocks but some information (atmosphere altitude, landable, no water) is inaccessible in the persistent file. Therefore they are all listed here.

The bodies objects : 
- **name** : string identifier of the body
- **id** : internal ID for other modules
- **translation** : locale oriented name of the body to help translation
- **atmosphere** : boolean to tell if the body can do atmosphere science experiments
- **water** : boolean to tell if the body can do splashed science experiments
- **landable** : boolean to tell if the body can do landed science experiments
- **atmosphereLimit** : lower and upper limit of the atmosphere to tell the flying limits of science experiments in kilometers
- **spaceLimit** : low space limit for space experiments in kilometers
- **biomes** : on the fly list of strings to tell which biome has been found. If you want your biomes to be sorted, you can use this table.
- [**filtername**] : display name of the filter if you want to restrain biomes
- [**showBiomes**] : list of biomes to restrain
- [**hideBiomes**] : list of biomes to hide
- **nbBiomes** : number of biomes the planet has to show a progression bar
- **xpMultiplier** : experience multiplier of the planet for Kerbal experience
- **xpSpecial** : since Kerbin handles experience differently, table showing the exerience muliplier for respectively planting a flag, landing, flying, suborbiting and orbiting Kerbin

# Kerbal Science

This program allows you to see your science progress in a more friendly way than the game proposes. It uses the [wiki's science format](https://wiki.kerbalspaceprogram.com/wiki/Science#Possible_combinations_of_Activity.2C_Situation.2C_and_Biome) which displays all science for each biome and shows only discovered science and bodies

![Visual demo kerbal science](https://vrac.goufastyle.org/pr/demo/kerbalscience/kerbalscience.jpg)

If you want to give it a try, go to [the demo website]( https://vrac.goufastyle.org/pr/demo/kerbalscience/kerbalscience.php)

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
title = étude de matériaux de Piste de décollage
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
* In french we also have apostrophes : de Kerbin => d'Eeloo and the title string can mix ` and '

Of course the solution could be to list all the biomes. but the script is supposed to be self learning, to encourage exploration, and avoid this writing task...

## Asteroids

Asteroids have a display name (ISL-245) and an internal name (_Potatoid2019). The matching is in another file.

Science on asteroids is done per biome, situation and body, which makes it difficult to sort with existing biomes. The choice made was to include asteroids at the bottom of the biome list since for the moment there is only one type of experience per asteroid

Of course you can fork the code to display data on your own using the first table generated.

## JSON export
You can export all the science found in your savefile in this format by clicking on the "Toggle JSON" button.

# Tourist planner
This view show ALL your tourist missions to eventually regroup them in one flight and especially check if some crazy ones wants to land on Eve...

![Visual demo kerbal science](https://vrac.goufastyle.org/pr/demo/kerbalscience/touristplanner.jpg)

If you want to give it a try, go to [the demo website]( https://vrac.goufastyle.org/pr/demo/kerbalscience/touristplanner.php)

## Legend and navigation

The result HTML page consists of these elements : 

* Planet list
* List of contracts, inactive (grey) and accepted (yellow)
* List of kerbals per contract, their destination and their reward per activity or full trip
* A quick view of planets per contract

A special style is given on activities involving flying or landing on bodies

## Database

The program looks for contracts in the savefile

```
CONTRACT
{
	guid = 4dd089c2-8a0b-47cc-bba8-5fec6c4afee8
	type = TourismContract
	prestige = 2
	seed = -1243464073
	state = Active
	viewed = Read
	agent = SysReac SARL
	agentName = Reaction Systems Ltd
	deadlineType = Floating
	expiryType = Floating
	values = 129600,368064000,0,0,0,0,26.08696,15,1172718171.85616,1172590138.14069,1540654138.14069,0
	preposition = du système solaire
	homeDestinations = False
	isGeeAdventure = False
	tourists = Mardena Kerman|Nordid Kerman|Dotorinne Kerman|Claubas Kerman|Comal Kerman|Taulas Kerman
	PARAM
	{
		name = KerbalTourParameter
		state = Incomplete
		disableOnStateChange = False
		allowPartialFailure = True
		values = 351000,0,0,0,0
		kerbalName = Dotorinne Kerman
		kerbalGender = Female
		PARAM
		{
			name = KerbalDestinationParameter
			enabled = False
			state = Complete
			values = 0,0,0,0,0
			targetBody = 1
			targetType = Suborbit
			kerbalName = Dotorinne Kerman
		}
		PARAM
		{
			name = KerbalDestinationParameter
			state = Incomplete
			values = 29250,0,0,0,0
			targetBody = 15
			targetType = Orbit
			kerbalName = Dotorinne Kerman
		}
	}
	...
}
```
and arranges them in an object form

```json
[{
	"guid": "4dd089c2-8a0b-47cc-bba8-5fec6c4afee8",
	"state": "Active",
	"values": [
		"129600",
		"368064000",
		"0",
		"0",
		"0",
		"0",
		"26.08696",
		"15",
		"1172718171.85616",
		"1172590138.14069",
		"1540654138.14069",
		"0"
	],
	"kerbalList": [
		{
			"kerbalName": "Dotorinne Kerman",
			"price": "351000",
			"activityList": [
				{
					"targetBody": "1",
					"targetType": "Suborbit",
					"price": "0",
					"state": "Complete",
					"values": null,
					"agent": null
				},
				{
					"targetBody": "15",
					"targetType": "Orbit",
					"price": "29250",
					"state": "Incomplete",
					"values": null,
						   "agent": null
				}
			]
		},
		...			
	],
	"agent": "SysReac SARL"
},
...]
```


# Kerbal XP

This program shows the situation of Kerbals in order to gain XP according to their current mission and their flight log.

![Visual demo kerbal science](https://vrac.goufastyle.org/pr/demo/kerbalscience/kerbalxp.jpg)

If you want to give it a try, go to [the demo website]( https://vrac.goufastyle.org/pr/demo/kerbalscience/kerbalxp.php)

## Legend
On the left is the flight history to show the current experience of the kerbal, and on the right the pending flight log that will be counted once retrieved on Kerbin or when applied in a science lab.

## Database

The program simply searches for a Kerbal's history and pending log and then applies all the multipliers. Again, Kerbin has a special way of managing experience.

```
KERBAL
{
	name = Delmonde Kerman
	gender = Female
	type = Crew
	trait = Scientist
	...
	CAREER_LOG
	{
		flight = 4
		0 = Flight,Kerbin
		0 = Suborbit,Kerbin
		0 = Orbit,Kerbin
		0 = Flyby,Minmus
		...
		3 = Orbit,Eve
		3 = Flyby,Kerbin
		3 = Flyby,Mun
		3 = Escape,Mun
		3 = Land,Kerbin
		3 = Recover
	}
	FLIGHT_LOG
	{
		flight = 4
		4 = Flight,Kerbin
		4 = Suborbit,Kerbin
		4 = Orbit,Kerbin
		4 = Escape,Kerbin
		...
		4 = ExitVessel,Eve
		4 = PlantFlag,Eve
		4 = BoardVessel,Eve
	}
}
```