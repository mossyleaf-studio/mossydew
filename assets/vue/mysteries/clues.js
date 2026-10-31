import { columnLetter } from './board.js';

const person = (clue, plan) => plan.people[clue.person];
const other = (clue, plan) => plan.people[clue.other];
const row = (clue) => clue.number + 1;
const column = (clue) => columnLetter(clue.number);
const roomPhrase = (clue, plan) => `mysteries.rooms.${plan.kinds[clue.room]}.in`;
const furniturePhrase = (variant) => (clue) => `mysteries.furniture.${clue.furniture}.${variant}`;
const count = (clue) => clue.number;
const than = (clue, plan) => `mysteries.than.${/^[aeiouàâäéèêëîïôöûü]/i.test(other(clue, plan)) ? 'vowel' : 'consonant'}`;
const someone = (clue) => `mysteries.someone.${clue.gender}`;
const subjectGender = (clue, gender) => gender;
const ownGender = (clue) => clue.gender;

const MESSAGES = {
    in_room: { params: { person }, phrases: { room: roomPhrase } },
    on_furniture: { params: { person }, phrases: { furniture: furniturePhrase('on') } },
    beside_furniture: { params: { person }, phrases: { furniture: furniturePhrase('beside') } },
    in_row: { params: { person, row } },
    in_column: { params: { person, column } },
    same_room: { params: { person, other } },
    north_of: { params: { person, other }, phrases: { than } },
    west_of: { params: { person, other }, phrases: { than } },
    empty_room: { phrases: { room: roomPhrase } },
    room_holds: { phrases: { room: roomPhrase }, plural: count },
    alone_with_murderer: { params: { person } },
    north_by: { params: { person, other }, phrases: { than }, plural: count },
    west_by: { params: { person, other }, phrases: { than }, plural: count },
    furniture_north: { params: { person }, phrases: { furniture: furniturePhrase('a') }, plural: count },
    furniture_south: { params: { person }, phrases: { furniture: furniturePhrase('a') }, plural: count },
    furniture_east: { params: { person }, phrases: { furniture: furniturePhrase('a') }, plural: count },
    furniture_west: { params: { person }, phrases: { furniture: furniturePhrase('a') }, plural: count },
    alone: { params: { person }, variant: subjectGender },
    alone_with: { params: { person }, phrases: { someone }, variant: subjectGender },
    grid_corner: { params: { person } },
    room_corner: { params: { person } },
    only_on: { params: { person }, phrases: { furniture: furniturePhrase('on') } },
    room_has: { params: { person }, phrases: { furniture: furniturePhrase('a') } },
    not_beside: { params: { person }, phrases: { furniture: furniturePhrase('none') } },
    someone_on: { params: { person }, phrases: { someone, furniture: furniturePhrase('on') } },
    someone_beside: { params: { person }, phrases: { furniture: furniturePhrase('beside') } },
    same_diagonal: { params: { person, other } },
    room_at_least: { phrases: { room: roomPhrase }, plural: count },
    north_of_furniture: { params: { person }, phrases: { furniture: furniturePhrase('than') } },
    south_of_furniture: { params: { person }, phrases: { furniture: furniturePhrase('than') } },
    east_of_furniture: { params: { person }, phrases: { furniture: furniturePhrase('than') } },
    west_of_furniture: { params: { person }, phrases: { furniture: furniturePhrase('than') } },
    seat_count: { phrases: { furniture: furniturePhrase('on') }, plural: count, variant: (clue) => clue.bound },
    every_room_occupied: {},
    room_cap: { plural: count },
    gender_seated: { phrases: { furniture: furniturePhrase('on') }, plural: count, variant: ownGender },
    pairing: { variant: ownGender },
};

const resolve = (resolvers = {}, clue, plan) => Object.fromEntries(Object.entries(resolvers).map(([name, resolver]) => [name, resolver(clue, plan)]));

function clueMessage(fact, plan, gender, pronoun) {
    const { params, phrases, plural, variant } = MESSAGES[fact.kind];
    const named = resolve(params, fact, plan);
    const phrased = resolve(phrases, fact, plan);
    if (pronoun && 'person' in named) {
        delete named.person;
        phrased.person = `mysteries.pronoun.${gender}`;
    }

    return {
        key: variant ? `mysteries.sentence.${fact.kind}.${variant(fact, gender)}` : `mysteries.sentence.${fact.kind}`,
        params: named,
        phrases: phrased,
        plural: plural?.(fact),
    };
}

export function clueMessages(clue, plan) {
    const gender = plan.genders[clue.person];
    const first = clueMessage(clue, plan, gender, false);

    const pronoun = clue.then?.companion || plan.genders[clue.other] !== gender;

    return clue.then ? [first, clueMessage(clue.then, plan, clue.then.companion ? clue.gender : gender, pronoun)] : [first];
}

function sentence({ key, params, phrases, plural }, t) {
    const named = { ...params, ...Object.fromEntries(Object.entries(phrases).map(([name, phrase]) => [name, t(phrase, params)])) };

    return plural === undefined ? t(key, named) : t(key, named, plural);
}

export function clueText(clue, plan, t) {
    return clueMessages(clue, plan).map((message) => sentence(message, t)).join(' ');
}
