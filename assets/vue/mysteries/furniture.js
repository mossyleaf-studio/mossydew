import { Armchair, Bath, Bed, CookingPot, LampFloor, LibraryBig, Piano, PlantPot, Sofa, Toilet, Tv, WashingMachine } from '../icons.js';
import { SEATS } from './board.js';

export const FURNITURE = {
    armchair: Armchair,
    sofa: Sofa,
    bed: Bed,
    plant: PlantPot,
    books: LibraryBig,
    bath: Bath,
    toilet: Toilet,
    tv: Tv,
    piano: Piano,
    lamp: LampFloor,
    stove: CookingPot,
    washer: WashingMachine,
};

export const BLOCKERS = Object.keys(FURNITURE).filter((piece) => !SEATS.includes(piece));
