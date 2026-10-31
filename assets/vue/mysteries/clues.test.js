import { createI18n } from 'vue-i18n';
import { describe, expect, it } from 'vitest';
import en from '../i18n/en/mysteries.json';
import fr from '../i18n/fr/mysteries.json';
import { clueMessages, clueText } from './clues.js';

const plan = { people: ['Aster', 'Basil', 'Clover', 'Élodie', 'Hazel'], genders: ['female', 'male', 'female', 'female', 'female'], kinds: ['kitchen', 'library'] };
const clueMessage = (clue) => {
    const messages = clueMessages(clue, plan);
    expect(messages).toHaveLength(1);

    return messages[0];
};

describe('clueMessages', () => {
    it('names the person and points at the room phrase', () => {
        expect(clueMessage({ kind: 'in_room', person: 2, room: 1 })).toEqual({
            key: 'mysteries.sentence.in_room',
            params: { person: 'Clover' },
            phrases: { room: 'mysteries.rooms.library.in' },
        });
    });

    it('points at the seat phrase for a person on furniture', () => {
        expect(clueMessage({ kind: 'on_furniture', person: 0, furniture: 'armchair' })).toEqual({
            key: 'mysteries.sentence.on_furniture',
            params: { person: 'Aster' },
            phrases: { furniture: 'mysteries.furniture.armchair.on' },
        });
    });

    it('points at the beside phrase for a person next to furniture', () => {
        expect(clueMessage({ kind: 'beside_furniture', person: 1, furniture: 'plant' })).toEqual({
            key: 'mysteries.sentence.beside_furniture',
            params: { person: 'Basil' },
            phrases: { furniture: 'mysteries.furniture.plant.beside' },
        });
    });

    it('counts rows from one and names columns by letter', () => {
        expect(clueMessage({ kind: 'in_row', person: 0, number: 0 }).params).toEqual({ person: 'Aster', row: 1 });
        expect(clueMessage({ kind: 'in_column', person: 0, number: 2 }).params).toEqual({ person: 'Aster', column: 'C' });
    });

    it('names both people in comparisons', () => {
        for (const kind of ['same_room', 'same_diagonal']) {
            expect(clueMessage({ kind, person: 0, other: 1 })).toEqual({
                key: `mysteries.sentence.${kind}`,
                params: { person: 'Aster', other: 'Basil' },
                phrases: {},
            });
        }
    });

    it('compares with the other person through a phrase that knows whether their name starts with a vowel', () => {
        for (const kind of ['north_of', 'west_of']) {
            expect(clueMessage({ kind, person: 0, other: 1 })).toEqual({
                key: `mysteries.sentence.${kind}`,
                params: { person: 'Aster', other: 'Basil' },
                phrases: { than: 'mysteries.than.consonant' },
            });
            expect(clueMessage({ kind, person: 1, other: 0 }).phrases).toEqual({ than: 'mysteries.than.vowel' });
        }
    });

    it('needs no person to say a room is empty', () => {
        expect(clueMessage({ kind: 'empty_room', room: 0 })).toEqual({
            key: 'mysteries.sentence.empty_room',
            params: {},
            phrases: { room: 'mysteries.rooms.kitchen.in' },
        });
    });

    it('picks the plural form from the head count of a room', () => {
        expect(clueMessage({ kind: 'room_holds', room: 1, number: 3 })).toEqual({
            key: 'mysteries.sentence.room_holds',
            params: {},
            phrases: { room: 'mysteries.rooms.library.in' },
            plural: 3,
        });
    });

    it('names the victim alone with the murderer', () => {
        expect(clueMessage({ kind: 'alone_with_murderer', person: 2 })).toEqual({
            key: 'mysteries.sentence.alone_with_murderer',
            params: { person: 'Clover' },
            phrases: {},
        });
    });

    it('picks the plural form from how many rows or columns apart two people are', () => {
        for (const kind of ['north_by', 'west_by']) {
            expect(clueMessage({ kind, person: 1, other: 0, number: 2 })).toEqual({
                key: `mysteries.sentence.${kind}`,
                params: { person: 'Basil', other: 'Aster' },
                phrases: { than: 'mysteries.than.vowel' },
                plural: 2,
            });
        }
    });

    it('points at the furniture phrase and picks the plural form from how many squares away it stands', () => {
        for (const kind of ['furniture_north', 'furniture_south', 'furniture_east', 'furniture_west']) {
            expect(clueMessage({ kind, person: 0, furniture: 'piano', number: 3 })).toEqual({
                key: `mysteries.sentence.${kind}`,
                params: { person: 'Aster' },
                phrases: { furniture: 'mysteries.furniture.piano.a' },
                plural: 3,
            });
        }
    });

    it('agrees the sentence of someone alone with their gender', () => {
        expect(clueMessage({ kind: 'alone', person: 2 })).toEqual({
            key: 'mysteries.sentence.alone.female',
            params: { person: 'Clover' },
            phrases: {},
        });
    });

    it('names the gender of the one person someone was alone with', () => {
        expect(clueMessage({ kind: 'alone_with', person: 1, gender: 'female' })).toEqual({
            key: 'mysteries.sentence.alone_with.male',
            params: { person: 'Basil' },
            phrases: { someone: 'mysteries.someone.female' },
        });
    });

    it('names the person on a corner of the grid or of their room', () => {
        for (const kind of ['grid_corner', 'room_corner']) {
            expect(clueMessage({ kind, person: 0 })).toEqual({
                key: `mysteries.sentence.${kind}`,
                params: { person: 'Aster' },
                phrases: {},
            });
        }
    });

    it('points at the seat phrase for the only person on that kind of seat', () => {
        expect(clueMessage({ kind: 'only_on', person: 0, furniture: 'bed' })).toEqual({
            key: 'mysteries.sentence.only_on',
            params: { person: 'Aster' },
            phrases: { furniture: 'mysteries.furniture.bed.on' },
        });
    });

    it('follows a fact with a second one about the same person, named by a pronoun', () => {
        expect(clueMessages({ kind: 'in_row', person: 0, number: 3, then: { kind: 'in_room', person: 0, room: 0 } }, plan)).toEqual([
            { key: 'mysteries.sentence.in_row', params: { person: 'Aster', row: 4 }, phrases: {} },
            { key: 'mysteries.sentence.in_room', params: {}, phrases: { person: 'mysteries.pronoun.female', room: 'mysteries.rooms.kitchen.in' } },
        ]);
    });

    it('keeps the name in the second fact when the first one names someone else of the same gender', () => {
        expect(clueMessages({ kind: 'same_diagonal', person: 0, other: 2, then: { kind: 'in_row', person: 0, number: 1 } }, plan)[1]).toEqual({
            key: 'mysteries.sentence.in_row',
            params: { person: 'Aster', row: 2 },
            phrases: {},
        });
        expect(clueMessages({ kind: 'same_diagonal', person: 0, other: 1, then: { kind: 'in_row', person: 0, number: 1 } }, plan)[1].phrases).toEqual({ person: 'mysteries.pronoun.female' });
    });

    it('agrees a second fact with the gender of the person it is about', () => {
        expect(clueMessages({ kind: 'in_column', person: 1, number: 0, then: { kind: 'alone', person: 1 } }, plan)[1]).toEqual({
            key: 'mysteries.sentence.alone.male',
            params: {},
            phrases: { person: 'mysteries.pronoun.male' },
        });
    });

    it('follows a fact with a plain sentence about a room', () => {
        expect(clueMessages({ kind: 'same_room', person: 0, other: 1, then: { kind: 'room_holds', room: 1, number: 3 } }, plan)).toEqual([
            { key: 'mysteries.sentence.same_room', params: { person: 'Aster', other: 'Basil' }, phrases: {} },
            { key: 'mysteries.sentence.room_holds', params: {}, phrases: { room: 'mysteries.rooms.library.in' }, plural: 3 },
        ]);
    });

    it('speaks of the companion of someone alone with one person by the pronoun of their gender', () => {
        expect(clueMessages({ kind: 'alone_with', person: 0, gender: 'male', then: { kind: 'on_furniture', furniture: 'armchair', companion: true } }, plan)).toEqual([
            { key: 'mysteries.sentence.alone_with.female', params: { person: 'Aster' }, phrases: { someone: 'mysteries.someone.male' } },
            { key: 'mysteries.sentence.on_furniture', params: {}, phrases: { person: 'mysteries.pronoun.male', furniture: 'mysteries.furniture.armchair.on' } },
        ]);
    });

    it('names the companion by pronoun even in a sentence that points at furniture', () => {
        expect(clueMessages({ kind: 'alone_with', person: 2, gender: 'female', then: { kind: 'furniture_east', furniture: 'piano', number: 2, companion: true } }, plan)[1]).toEqual({
            key: 'mysteries.sentence.furniture_east',
            params: {},
            phrases: { person: 'mysteries.pronoun.female', furniture: 'mysteries.furniture.piano.a' },
            plural: 2,
        });
    });

    it('elides before a name starting with an accented vowel but not before an h', () => {
        expect(clueMessage({ kind: 'north_of', person: 0, other: 3 }).phrases).toEqual({ than: 'mysteries.than.vowel' });
        expect(clueMessage({ kind: 'north_of', person: 0, other: 4 }).phrases).toEqual({ than: 'mysteries.than.consonant' });
    });

    it('picks the plural form from the least head count of a room', () => {
        expect(clueMessage({ kind: 'room_at_least', room: 0, number: 2 })).toEqual({
            key: 'mysteries.sentence.room_at_least',
            params: {},
            phrases: { room: 'mysteries.rooms.kitchen.in' },
            plural: 2,
        });
    });

    it('compares a person with a kind of furniture through its comparison phrase', () => {
        for (const kind of ['north_of_furniture', 'south_of_furniture', 'east_of_furniture', 'west_of_furniture']) {
            expect(clueMessage({ kind, person: 1, furniture: 'plant' })).toEqual({
                key: `mysteries.sentence.${kind}`,
                params: { person: 'Basil' },
                phrases: { furniture: 'mysteries.furniture.plant.than' },
            });
        }
    });

    it('points at the furniture standing in the room of a person', () => {
        expect(clueMessage({ kind: 'room_has', person: 1, furniture: 'piano' })).toEqual({
            key: 'mysteries.sentence.room_has',
            params: { person: 'Basil' },
            phrases: { furniture: 'mysteries.furniture.piano.a' },
        });
    });

    it('points at the negative phrase for a person next to no such furniture', () => {
        expect(clueMessage({ kind: 'not_beside', person: 0, furniture: 'lamp' })).toEqual({
            key: 'mysteries.sentence.not_beside',
            params: { person: 'Aster' },
            phrases: { furniture: 'mysteries.furniture.lamp.none' },
        });
    });

    it('names the gender of someone else on a seat in the room of a person', () => {
        expect(clueMessage({ kind: 'someone_on', person: 0, gender: 'male', furniture: 'bed' })).toEqual({
            key: 'mysteries.sentence.someone_on',
            params: { person: 'Aster' },
            phrases: { someone: 'mysteries.someone.male', furniture: 'mysteries.furniture.bed.on' },
        });
    });

    it('points at the beside phrase for someone else next to furniture in the room of a person', () => {
        expect(clueMessage({ kind: 'someone_beside', person: 1, furniture: 'books' })).toEqual({
            key: 'mysteries.sentence.someone_beside',
            params: { person: 'Basil' },
            phrases: { furniture: 'mysteries.furniture.books.beside' },
        });
    });

    it('keeps the name of the other person after a pronoun subject', () => {
        expect(clueMessages({ kind: 'in_row', person: 1, number: 0, then: { kind: 'west_of', person: 1, other: 0 } }, plan)[1]).toEqual({
            key: 'mysteries.sentence.west_of',
            params: { other: 'Aster' },
            phrases: { person: 'mysteries.pronoun.male', than: 'mysteries.than.vowel' },
        });
    });
});

describe('clueText', () => {
    const translator = (locale) => createI18n({ legacy: false, locale, messages: { en: { mysteries: en }, fr: { mysteries: fr } } }).global.t;

    it('joins the sentences of a card', () => {
        const card = { kind: 'in_row', person: 0, number: 3, then: { kind: 'west_of', person: 0, other: 1 } };

        expect(clueText(card, plan, translator('en'))).toBe('Aster was in row 4. She was further west than Basil.');
        expect(clueText(card, plan, translator('fr'))).toBe('Aster était sur la ligne 4. Elle était plus à l’ouest que Basil.');
    });

    it('elides the French comparison before a name starting with a vowel', () => {
        expect(clueText({ kind: 'north_by', person: 1, other: 0, number: 2 }, plan, translator('fr'))).toBe('Basil était exactement 2 lignes plus au nord qu’Aster.');
        expect(clueText({ kind: 'north_of', person: 1, other: 3 }, plan, translator('fr'))).toBe('Basil était plus au nord qu’Élodie.');
        expect(clueText({ kind: 'north_of', person: 1, other: 0 }, plan, translator('en'))).toBe('Basil was further north than Aster.');
    });

    it('agrees a French sentence with the gender of its subject and names the one other person by theirs', () => {
        const card = { kind: 'alone_with', person: 0, gender: 'male', then: { kind: 'on_furniture', furniture: 'armchair', companion: true } };

        expect(clueText(card, plan, translator('en'))).toBe('Aster was alone with a man. He was in an armchair.');
        expect(clueText(card, plan, translator('fr'))).toBe('Aster était seule avec un homme. Il était dans un fauteuil.');
    });

    it('denies every piece of a kind of furniture next to someone', () => {
        const card = { kind: 'in_row', person: 1, number: 0, then: { kind: 'not_beside', person: 1, furniture: 'piano' } };

        expect(clueText(card, plan, translator('en'))).toBe('Basil was in row 1. He was not next to any piano.');
        expect(clueText(card, plan, translator('fr'))).toBe('Basil était sur la ligne 1. Il n’était à côté d’aucun piano.');
        expect(clueText({ kind: 'not_beside', person: 0, furniture: 'plant' }, plan, translator('fr'))).toBe('Aster n’était à côté d’aucune plante.');
    });

    it('compares with furniture after its article, eliding in French', () => {
        const card = { kind: 'in_row', person: 0, number: 0, then: { kind: 'south_of_furniture', person: 0, furniture: 'armchair' } };

        expect(clueText(card, plan, translator('en'))).toBe('Aster was in row 1. She was further south than an armchair.');
        expect(clueText(card, plan, translator('fr'))).toBe('Aster était sur la ligne 1. Elle était plus au sud qu’un fauteuil.');
        expect(clueText({ kind: 'west_of_furniture', person: 1, furniture: 'plant' }, plan, translator('fr'))).toBe('Basil était plus à l’ouest qu’une plante.');
    });

    it('gives the least head count of a room', () => {
        const card = { kind: 'in_row', person: 1, number: 0, then: { kind: 'room_at_least', room: 1, number: 3 } };

        expect(clueText(card, plan, translator('en'))).toBe('Basil was in row 1. At least 3 people were in the library.');
        expect(clueText(card, plan, translator('fr'))).toBe('Basil était sur la ligne 1. Au moins 3 personnes étaient dans la bibliothèque.');
    });

    it('compares the companion with furniture by their pronoun', () => {
        const card = { kind: 'alone_with', person: 0, gender: 'male', then: { kind: 'south_of_furniture', furniture: 'stove', companion: true } };

        expect(clueText(card, plan, translator('en'))).toBe('Aster was alone with a man. He was further south than a stove.');
        expect(clueText(card, plan, translator('fr'))).toBe('Aster était seule avec un homme. Il était plus au sud qu’une cuisinière.');
    });
});

describe('board rules', () => {
    const translator = (locale) => createI18n({ legacy: false, locale, messages: { en: { mysteries: en }, fr: { mysteries: fr } } }).global.t;
    const say = (rule) => ['en', 'fr'].map((locale) => clueText(rule, plan, translator(locale)));

    it('picks the sentence of a seat count from its bound and its plural form from the number', () => {
        expect(clueMessages({ kind: 'seat_count', furniture: 'sofa', number: 2, bound: 'at_least' }, plan)).toEqual([{
            key: 'mysteries.sentence.seat_count.at_least',
            params: {},
            phrases: { furniture: 'mysteries.furniture.sofa.on' },
            plural: 2,
        }]);
        expect(say({ kind: 'seat_count', furniture: 'armchair', number: 1, bound: 'exactly' })).toEqual(['Exactly one person was in an armchair.', 'Exactement une personne était dans un fauteuil.']);
        expect(say({ kind: 'seat_count', furniture: 'bed', number: 2, bound: 'at_least' })).toEqual(['At least 2 people were in a bed.', 'Au moins 2 personnes étaient dans un lit.']);
        expect(say({ kind: 'seat_count', furniture: 'sofa', number: 1, bound: 'at_most' })).toEqual(['At most one person was on a sofa.', 'Au plus une personne était sur un canapé.']);
        expect(say({ kind: 'seat_count', furniture: 'sofa', number: 0, bound: 'exactly' })).toEqual(['Nobody was on a sofa.', 'Personne n’était sur un canapé.']);
    });

    it('says every room was occupied', () => {
        expect(say({ kind: 'every_room_occupied' })).toEqual(['Every room held at least one person.', 'Aucune pièce n’était vide.']);
    });

    it('caps the head count of every room', () => {
        expect(say({ kind: 'room_cap', number: 3 })).toEqual(['No room held more than 3 people.', 'Aucune pièce n’accueillait plus de 3 personnes.']);
        expect(say({ kind: 'room_cap', number: 1 })).toEqual(['No room held more than one person.', 'Aucune pièce n’accueillait plus d’une personne.']);
    });

    it('says a room was empty', () => {
        expect(say({ kind: 'empty_room', room: 1 })).toEqual(['Nobody was in the library.', 'Personne n’était dans la bibliothèque.']);
    });

    it('counts the women or the men on a kind of seat', () => {
        expect(clueMessages({ kind: 'gender_seated', gender: 'female', furniture: 'sofa', number: 2 }, plan)[0].key).toBe('mysteries.sentence.gender_seated.female');
        expect(say({ kind: 'gender_seated', gender: 'female', furniture: 'sofa', number: 2 })).toEqual(['Exactly 2 women were on a sofa.', 'Exactement 2 femmes étaient sur un canapé.']);
        expect(say({ kind: 'gender_seated', gender: 'male', furniture: 'bed', number: 1 })).toEqual(['Exactly one man was in a bed.', 'Exactement un homme était dans un lit.']);
        expect(say({ kind: 'gender_seated', gender: 'male', furniture: 'armchair', number: 0 })).toEqual(['No man was in an armchair.', 'Aucun homme n’était dans un fauteuil.']);
    });

    it('pairs everyone of a gender with someone of the other one', () => {
        expect(say({ kind: 'pairing', gender: 'male' })).toEqual(['Every man shared his room with a woman.', 'Chaque homme partageait sa pièce avec une femme.']);
        expect(say({ kind: 'pairing', gender: 'female' })).toEqual(['Every woman shared her room with a man.', 'Chaque femme partageait sa pièce avec un homme.']);
    });
});
