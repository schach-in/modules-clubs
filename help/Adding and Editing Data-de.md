<!--
# clubs module
# Adding and editing data, German
#
# Part of »Zugzwang Project«
# https://www.zugzwang.org/modules/clubs
#
# @author Gustaf Mossakowski <gustaf@koenige.org>
# @copyright Copyright © 2026 Gustaf Mossakowski
# @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
#
# Variables
# execute_bricks = if path clubs_school_add, path clubs_school_add, endif, if path clubs_kindergarten_add, path clubs_kindergarten_add, endif
-->

# Eintragen und Bearbeiten von Daten

Unter schach.in/deutschland kannst du alle **Schachvereine**, die dem
Deutschen Schachbund angeschlossen sind, viele **Schulen** mit
Schach-Arbeitsgemeinschaften und einige **Kindergärten** oder
**Kindertagesstätten** mit Schachangebot finden.

## Vereine

Alle Vereine im Deutschen Schachbund (DSB) werden teilautomatisch
aktualisiert. Die Mitgliederzahlen und Top Ten-Listen werden wöchentlich
jeweils am Mittwoch aktualisiert. Da es keine direkte Schnittstelle zu den
Vereinsdaten des DSB gibt, kann es bis zu zwei Monate dauern, bis ein neuer
Verein in der Liste auftaucht. Sollte dein Verein fehlen, kannst du dich
gerne [bei uns melden](/kontakt/).

Korrekturen an den Daten können auf jeder Vereinsseite unten unter »Daten
bearbeiten« vorgenommen werden.

## Schulen

An vielen Schulen gibt es Schach-Arbeitsgemeinschaften, andere
Schachangebote oder auch nur Teilnahmen an lokalen Schulschachturnieren.
Oft bestehen diese Angebote auch nur kurzzeitig. Da es keine zentralen
Listen gibt, sind wir hier auf deine Mitarbeit angewiesen:

%%% if path clubs_school_add %%%
[Meine Schule hinzufügen](%%% path clubs_school_add %%%)
%%% endif %%%

Korrekturen an den bereits vorhandenen Daten können auf jeder Schulseite
unten unter »Daten bearbeiten« vorgenommen werden.

## Kindergärten und Kindertagesstätten

Auch für die Kleinsten gibt es schon spielerische Schachangebote. Hier
haben wir aber nur wenige Daten, da es keine organisierten Wettkämpfe gibt.

%%% if path clubs_kindergarten_add %%%
[Meinen Kindergarten oder meine Kindertagesstätte hinzufügen](%%% path clubs_kindergarten_add %%%)
%%% endif %%%

Korrekturen an den bereits vorhandenen Daten können auf jeder
Kindergarten- oder Kitaseite unten unter »Daten bearbeiten«
vorgenommen werden.
