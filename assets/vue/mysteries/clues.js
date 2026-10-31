import { columnLetter } from './board.js';

const person = (clue, plan) => plan.people[clue.person];
const other = (clue, plan) => plan.people[clue.other];
const row = (clue) => clue.number + 1;
const column = (clue) => columnLetter(clue.number);
const roomPhrase = (clue, plan) => `mysteries.rooms.${plan.kinds[clue.room]}.in`;
const furniturePhrase = (variant) => (clue) => `mysteries.furniture.${clue.furniture}.${variant}`;
const headCount = (clue) => clue.number;

const MESSAGES = {
    in_room: { params: { person }, phrases: { room: roomPhrase } },
    on_furniture: { params: { person }, phrases: { furniture: furniturePhrase('on') } },
    beside_furniture: { params: { person }, phrases: { furniture: furniturePhrase('beside') } },
    in_row: { params: { person, row } },
    in_column: { params: { person, column } },
    same_room: { params: { person, other } },
    north_of: { params: { person, other } },
    west_of: { params: { person, other } },
    empty_room: { phrases: { room: roomPhrase } },
    room_holds: { phrases: { room: roomPhrase }, plural: headCount },
};

const resolve = (resolvers = {}, clue, plan) => Object.fromEntries(Object.entries(resolvers).map(([name, resolver]) => [name, resolver(clue, plan)]));

export function clueMessage(clue, plan) {
    const { params, phrases, plural } = MESSAGES[clue.kind];

    return {
        key: `mysteries.sentence.${clue.kind}`,
        params: resolve(params, clue, plan),
        phrases: resolve(phrases, clue, plan),
        plural: plural?.(clue),
    };
}
