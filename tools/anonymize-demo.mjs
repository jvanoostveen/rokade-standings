#!/usr/bin/env node

/**
 * Vervangt spelersnamen in de meegeleverde Rokade-demo door vaste pseudoniemen.
 *
 * De Rokade-export gebruikt de spelerpagina als identiteit (bijvoorbeeld
 * C13P10122.htm). Daardoor blijft een speler in een ranglijst, scoretabel en
 * zijn of haar detailpagina steeds dezelfde naam houden.
 */
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, normalize, resolve } from 'node:path';

const demoDir = resolve(process.argv[2] || 'demo');

const voornamen = [
	'Aafke', 'Abel', 'Adriaan', 'Aisha', 'Albert', 'Aletta', 'Amira', 'Anouk', 'Arjen', 'Auke',
	'Bente', 'Berend', 'Bregje', 'Casper', 'Celeste', 'Coen', 'Daan', 'Diede', 'Dirk', 'Eefje',
	'Elias', 'Elise', 'Esmee', 'Eva', 'Feline', 'Femke', 'Floris', 'Frank', 'Freek', 'Gijs',
	'Hanne', 'Hein', 'Helena', 'Hugo', 'Ilse', 'Imke', 'Iris', 'Ivo', 'Jasmijn', 'Jelle',
	'Jip', 'Jochem', 'Joris', 'Juno', 'Karin', 'Kees', 'Kiki', 'Koen', 'Lara', 'Lars',
	'Lieke', 'Linde', 'Loes', 'Lotte', 'Maaike', 'Maarten', 'Mees', 'Mila', 'Niels', 'Nienke',
	'Noor', 'Olivier', 'Pien', 'Pim', 'Quinten', 'Renske', 'Roos', 'Ruben', 'Saar', 'Sander',
	'Sanne', 'Sem', 'Sterre', 'Teun', 'Tessa', 'Thijs', 'Tobias', 'Udo', 'Vera', 'Vic',
	'Willem', 'Wouter', 'Xander', 'Yael', 'Yara', 'Yvonne', 'Zeger', 'Zoe',
];
const achternamen = [
	'Bakker', 'de Boer', 'Bos', 'Brouwer', 'de Bruin', 'de Graaf', 'de Groot', 'de Haan', 'de Jong', 'de Vries',
	'Dijkstra', 'Evers', 'Faber', 'van Gaalen', 'Groen', 'Hendriks', 'Hoekstra', 'Jansen', 'Koster', 'Kuiper',
	'van Loon', 'Meijer', 'Mulder', 'Nijland', 'Oosterhuis', 'Pieters', 'Postma', 'Prins', 'van der Put', 'Roos',
	'Schouten', 'Smit', 'Smittenaar', 'Sonneveld', 'Timmer', 'van Tol', 'Verbeek', 'Verhoeven', 'Visser', 'Vos',
	'van Wijk', 'Willems', 'Wolters', 'van Zanten', 'Zuidema', 'Aarts', 'Baars', 'Beekman', 'Bergman', 'van den Berg',
	'Bijl', 'Blom', 'van Dijk', 'Driessen', 'Eijck', 'van Es', 'Fransen', 'Gerritsen', 'Grootveld', 'van der Heijden',
	'Heijmans', 'Hofman', 'Huisman', 'Jacobs', 'Kampman', 'Kerkhof', 'Klein', 'Koning', 'Kruis', 'Lammers',
	'Leenders', 'Lemmens', 'Lindenberg', 'Maas', 'Martens', 'de Meijer', 'van der Meer', 'Molenaar', 'van den Oever', 'Otten',
	'Pietersen', 'van Rijn', 'Rietveld', 'Schaap', 'Scholten', 'Slagter', 'Smitveld', 'Snijders', 'Stolwijk', 'Talen',
	'Teunissen', 'Veldhuis', 'Vermeer', 'Vink', 'van der Vliet', 'van Veen', 'de Waard', 'Wessels', 'Wiersma', 'Wouters',
	'Zandstra', 'Zwart', 'Aalbers', 'Baas', 'Beumer', 'Boonstra', 'van Dam', 'Davelaar', 'van Doorn', 'Drost',
	'van Eeden', 'Engels', 'Fokkema', 'Gelderman', 'Geurts', 'Hagen', 'Harmsen', 'Hazenberg', 'Helleman', 'Hofstede',
	'van der Horst', 'Janssen', 'Klaassen', 'Kleijn', 'Kramer', 'van Kooten', 'Landman', 'Langeveld', 'Loonen', 'van der Laan',
	'Meijers', 'van Mierlo', 'Nauta', 'Nijhuis', 'Noordman', 'Oskam', 'Pannekoek', 'Pauwels', 'Reinders', 'Rijnsburger',
	'Ruiter', 'Schoenmaker', 'Sengers', 'Smeets', 'Stam', 'Steenbergen', 'Swaans', 'Terpstra', 'Veenstra', 'Velthuis',
	'Verburg', 'Verheul', 'Vermolen', 'Visscher', 'de Wit', 'van der Woude', 'Zandbergen', 'Zijlstra', 'Zuiderwijk', 'Zwartendijk',
	'Boon', 'Dekker', 'Giesen', 'Kusters', 'Lentink', 'Miedema', 'Oomens', 'Pennings', 'Rombouts', 'Schoemaker',
	'Timmermans', 'Uiterwijk', 'Veenman', 'Westerman', 'Zilverberg',
];

function verkorteAchternaam(achternaam) {
	const delen = achternaam.split(' ');
	const initiaal = delen.pop().charAt(0).toUpperCase();
	return delen.length ? `${delen.join(' ')} ${initiaal}` : initiaal;
}

function bestandenIn(map) {
	return readdirSync(map, { withFileTypes: true }).flatMap((item) => {
		const bestand = join(map, item.name);
		return item.isDirectory() ? bestandenIn(bestand) : [bestand];
	});
}

function isHtml(bestand) {
	return /\.html?$/i.test(bestand);
}

function platteTekst(html) {
	return html
		.replace(/<[^>]*>/g, '')
		.replace(/&nbsp;/gi, ' ')
		.replace(/&#160;|&#xa0;/gi, ' ')
		.replace(/&amp;/gi, '&')
		.replace(/\s+/g, ' ')
		.trim();
}

function identiteit(bestand, href) {
	return normalize(resolve(dirname(bestand), href.replace(/#.*$/, '')));
}

function escapeRegExp(tekst) {
	return tekst.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function naamPatroon(naam) {
	return escapeRegExp(naam).replace(/ +/g, '(?:\\s|&nbsp;|&#160;|&#xa0;)+');
}

class Verbindingen {
	constructor() {
		this.ouder = new Map();
	}

	voegToe(item) {
		if (!this.ouder.has(item)) this.ouder.set(item, item);
	}

	vind(item) {
		this.voegToe(item);
		const ouder = this.ouder.get(item);
		if (ouder === item) return item;
		const wortel = this.vind(ouder);
		this.ouder.set(item, wortel);
		return wortel;
	}

	verbind(eerste, tweede) {
		const a = this.vind(eerste);
		const b = this.vind(tweede);
		if (a !== b) this.ouder.set(b, a);
	}
}

const htmlBestanden = bestandenIn(demoDir).filter(isHtml);
const verbindingen = new Verbindingen();
const namenPerIdentiteit = new Map();
const identiteitenPerNaam = new Map();

function onthoudNaam(id, naam) {
	if (!naam) return;
	verbindingen.voegToe(id);
	if (!namenPerIdentiteit.has(id)) namenPerIdentiteit.set(id, new Set());
	namenPerIdentiteit.get(id).add(naam);
	if (!identiteitenPerNaam.has(naam)) identiteitenPerNaam.set(naam, new Set());
	identiteitenPerNaam.get(naam).add(id);
}

for (const bestand of htmlBestanden) {
	const inhoud = readFileSync(bestand, 'latin1');
	const linkPatroon = /<a\b[^>]*\bhref=(['"])([^'"]*C\d+P-?\d+\.html?)\1[^>]*>([\s\S]*?)<\/a>/gi;
	for (const match of inhoud.matchAll(linkPatroon)) {
		onthoudNaam(identiteit(bestand, match[2]), platteTekst(match[3]));
	}

	const spelerId = /C\d+P-?\d+\.html?$/i.test(bestand) ? normalize(bestand) : null;
	for (const match of inhoud.matchAll(/Rondenlijst van\s+([^<\r\n]+)/gi)) {
		if (spelerId) onthoudNaam(spelerId, platteTekst(match[1]));
	}
}

// Dezelfde weergegeven naam kan in een ander seizoen een andere bestandsnaam hebben.
// Verbind die records om de pseudoniemkeuze over de hele demo consistent te houden.
for (const ids of identiteitenPerNaam.values()) {
	const [eerste, ...rest] = ids;
	for (const id of rest) verbindingen.verbind(eerste, id);
}

const groepen = new Map();
for (const [id, namen] of namenPerIdentiteit) {
	const wortel = verbindingen.vind(id);
	if (!groepen.has(wortel)) groepen.set(wortel, new Set());
	for (const naam of namen) groepen.get(wortel).add(naam);
}

const gesorteerdeGroepen = [...groepen.values()].sort((a, b) => [...a][0].localeCompare([...b][0], 'nl'));
const aantallenPerVoornaam = new Map();
for (const [index] of gesorteerdeGroepen.entries()) {
	const voornaam = voornamen[index % voornamen.length];
	aantallenPerVoornaam.set(voornaam, (aantallenPerVoornaam.get(voornaam) || 0) + 1);
}

const vervangerPerNaam = new Map();
const gebruiktePseudoniemen = new Set();
let volgendPseudoniem = 0;
for (const [index, namen] of gesorteerdeGroepen.entries()) {
	const voornaam = voornamen[index % voornamen.length];
	let vervanger;
	do {
		const volgnummer = volgendPseudoniem++;
		if (volgnummer >= achternamen.length) {
			throw new Error('Te weinig pseudoniemen voor deze demo.');
		}
		vervanger = aantallenPerVoornaam.get(voornaam) > 1
			? `${voornaam} ${verkorteAchternaam(achternamen[volgnummer])}`
			: voornaam;
	} while (gebruiktePseudoniemen.has(vervanger));
	gebruiktePseudoniemen.add(vervanger);
	for (const naam of namen) vervangerPerNaam.set(naam, vervanger);
}

const namen = [...vervangerPerNaam.keys()].sort((a, b) => b.length - a.length);
const placeholders = new Map(namen.map((naam, index) => [naam, `__ROKADE_PSEUDONIEM_${index}__`]));
for (const bestand of htmlBestanden) {
	let inhoud = readFileSync(bestand, 'latin1');
	for (const naam of namen) {
		const patroon = new RegExp(`(^|[^A-Za-zÀ-ÿ])(${naamPatroon(naam)})(?=$|[^A-Za-zÀ-ÿ])`, 'g');
		inhoud = inhoud.replace(patroon, `$1${placeholders.get(naam)}`);
	}
	for (const naam of namen) inhoud = inhoud.replaceAll(placeholders.get(naam), vervangerPerNaam.get(naam));
	writeFileSync(bestand, inhoud, 'latin1');
}

let placeholdersOver = 0;
for (const bestand of htmlBestanden) {
	const inhoud = readFileSync(bestand, 'latin1');
	if (inhoud.includes('__ROKADE_PSEUDONIEM_')) placeholdersOver += 1;
}

if (placeholdersOver) {
	throw new Error(`Anonimisering onvolledig: ${placeholdersOver} tijdelijke pseudoniem(en) zijn blijven staan.`);
}

console.log(`${htmlBestanden.length} HTML-bestanden geanonimiseerd; ${vervangerPerNaam.size} namen vervangen.`);
