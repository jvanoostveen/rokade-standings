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
	'Aurel', 'Bexley', 'Cyran', 'Daxen', 'Elvar', 'Faryn', 'Gavren', 'Helix', 'Ilyra', 'Jorven',
	'Korin', 'Lioren', 'Mavik', 'Nirel', 'Orvyn', 'Paxen', 'Quorin', 'Riven', 'Sorel', 'Taryn',
	'Ulan', 'Veyra', 'Wexen', 'Xyra', 'Yorin', 'Zavik',
];
const achternamen = [
	'Avens', 'Belvar', 'Corven', 'Dorsen', 'Elyon', 'Faron', 'Galdor', 'Haven', 'Ivers', 'Jorin',
	'Keldan', 'Lorven', 'Merrow', 'Noran', 'Orsel', 'Pavel', 'Quaren', 'Rovik', 'Seldon', 'Tavor',
	'Uvren', 'Varen', 'Weylin', 'Xandor', 'Yoren', 'Zerik',
];

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

function bevatNaam(tekst, naam) {
	return new RegExp(`(^|[^A-Za-zÀ-ÿ])${naamPatroon(naam)}(?=$|[^A-Za-zÀ-ÿ])`, 'i').test(tekst);
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
	const linkPatroon = /<a\b[^>]*\bhref=(['"])([^'"]*C\d+P\d+\.html?)\1[^>]*>([\s\S]*?)<\/a>/gi;
	for (const match of inhoud.matchAll(linkPatroon)) {
		onthoudNaam(identiteit(bestand, match[2]), platteTekst(match[3]));
	}

	const spelerId = /C\d+P\d+\.html?$/i.test(bestand) ? normalize(bestand) : null;
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

const vervangerPerNaam = new Map();
let volgendPseudoniem = 0;
for (const [index, namen] of [...groepen.values()].sort((a, b) => [...a][0].localeCompare([...b][0], 'nl')).entries()) {
	let vervanger;
	do {
		const volgnummer = volgendPseudoniem++;
		vervanger = `${voornamen[volgnummer % voornamen.length]} ${achternamen[Math.floor(volgnummer / voornamen.length) % achternamen.length]}`;
	} while ([...identiteitenPerNaam.keys()].some((naam) => bevatNaam(vervanger, naam)));
	for (const naam of namen) vervangerPerNaam.set(naam, vervanger);
}

const namen = [...vervangerPerNaam.keys()].sort((a, b) => b.length - a.length);
for (const bestand of htmlBestanden) {
	let inhoud = readFileSync(bestand, 'latin1');
	for (const naam of namen) {
		const patroon = new RegExp(`(^|[^A-Za-zÀ-ÿ])(${naamPatroon(naam)})(?=$|[^A-Za-zÀ-ÿ])`, 'g');
		inhoud = inhoud.replace(patroon, `$1${vervangerPerNaam.get(naam)}`);
	}
	writeFileSync(bestand, inhoud, 'latin1');
}

let resterend = 0;
for (const bestand of htmlBestanden) {
	const inhoud = readFileSync(bestand, 'latin1');
	for (const naam of namen) {
		if (bevatNaam(inhoud, naam)) resterend += 1;
	}
}

if (resterend) {
	throw new Error(`Anonimisering onvolledig: ${resterend} bekende na(a)m(en) komt/komen nog voor.`);
}

console.log(`${htmlBestanden.length} HTML-bestanden geanonimiseerd; ${vervangerPerNaam.size} namen vervangen.`);
