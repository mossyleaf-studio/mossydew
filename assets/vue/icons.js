import { h, ref } from 'vue';
import * as Lucide from '@lucide/vue';
import {
    PhArmchair, PhArrowElbowDownRight, PhArrowRight, PhArrowUp, PhBathtub, PhBed, PhBooks, PhCalendarDots, PhCaretDown, PhCaretLeft,
    PhCaretRight, PhChartBar, PhCheck, PhCheckCircle, PhCloudFog, PhCloudRain, PhCompass, PhCookingPot, PhCouch, PhDotsThree, PhDrop,
    PhDropHalfBottom, PhFire, PhFolderSimplePlus, PhGear, PhGridFour, PhLamp, PhLeaf, PhList, PhListChecks, PhLock, PhMagnifyingGlass,
    PhPencilSimple, PhPianoKeys, PhPlant, PhPlus, PhPottedPlant, PhRecycle, PhRepeat, PhScissors, PhShovel, PhSignOut, PhSkull, PhSun,
    PhTelevision, PhTextAlignLeft, PhToilet, PhTrash, PhTray, PhTrophy, PhUserMinus, PhUsers, PhWarehouse, PhWashingMachine, PhX,
} from '@phosphor-icons/vue';
import { useSession } from './composables/useSession.js';

export const ICON_SETS = ['lucide', 'phosphor'];

export const iconSet = ref(useSession()?.icons ?? 'lucide');

function icon(lucide, phosphor, weight = 'bold') {
    const component = (props, { attrs }) => (iconSet.value === 'phosphor' ? h(phosphor, { weight, ...attrs }) : h(lucide, attrs));
    component.inheritAttrs = false;

    return component;
}

export const AlignLeft = icon(Lucide.AlignLeft, PhTextAlignLeft);
export const Armchair = icon(Lucide.Armchair, PhArmchair, 'duotone');
export const ArrowRight = icon(Lucide.ArrowRight, PhArrowRight);
export const ArrowUp = icon(Lucide.ArrowUp, PhArrowUp);
export const Bath = icon(Lucide.Bath, PhBathtub, 'duotone');
export const Bed = icon(Lucide.Bed, PhBed, 'duotone');
export const CalendarDays = icon(Lucide.CalendarDays, PhCalendarDots, 'duotone');
export const ChartColumn = icon(Lucide.ChartColumn, PhChartBar, 'duotone');
export const Check = icon(Lucide.Check, PhCheck);
export const ChevronDown = icon(Lucide.ChevronDown, PhCaretDown);
export const ChevronLeft = icon(Lucide.ChevronLeft, PhCaretLeft);
export const ChevronRight = icon(Lucide.ChevronRight, PhCaretRight);
export const CircleCheckBig = icon(Lucide.CircleCheckBig, PhCheckCircle, 'duotone');
export const CloudDrizzle = icon(Lucide.CloudDrizzle, PhCloudFog, 'duotone');
export const CloudRain = icon(Lucide.CloudRain, PhCloudRain, 'duotone');
export const Compass = icon(Lucide.Compass, PhCompass, 'duotone');
export const CookingPot = icon(Lucide.CookingPot, PhCookingPot, 'duotone');
export const CornerDownRight = icon(Lucide.CornerDownRight, PhArrowElbowDownRight);
export const Droplet = icon(Lucide.Droplet, PhDrop, 'duotone');
export const Droplets = icon(Lucide.Droplets, PhDropHalfBottom, 'duotone');
export const Ellipsis = icon(Lucide.Ellipsis, PhDotsThree);
export const Flame = icon(Lucide.Flame, PhFire, 'duotone');
export const FolderInput = icon(Lucide.FolderInput, PhFolderSimplePlus);
export const Grid2x2 = icon(Lucide.Grid2x2, PhGridFour, 'duotone');
export const Inbox = icon(Lucide.Inbox, PhTray, 'duotone');
export const LampFloor = icon(Lucide.LampFloor, PhLamp, 'duotone');
export const Leaf = icon(Lucide.Leaf, PhLeaf, 'duotone');
export const LibraryBig = icon(Lucide.LibraryBig, PhBooks, 'duotone');
export const ListChecks = icon(Lucide.ListChecks, PhListChecks);
export const Lock = icon(Lucide.Lock, PhLock, 'duotone');
export const LogOut = icon(Lucide.LogOut, PhSignOut);
export const Menu = icon(Lucide.Menu, PhList);
export const Pencil = icon(Lucide.Pencil, PhPencilSimple);
export const Piano = icon(Lucide.Piano, PhPianoKeys, 'duotone');
export const PlantPot = icon(Lucide.PlantPot, PhPottedPlant, 'duotone');
export const Plus = icon(Lucide.Plus, PhPlus);
export const Recycle = icon(Lucide.Recycle, PhRecycle, 'duotone');
export const Repeat = icon(Lucide.Repeat, PhRepeat);
export const Scissors = icon(Lucide.Scissors, PhScissors, 'duotone');
export const Search = icon(Lucide.Search, PhMagnifyingGlass, 'duotone');
export const Settings = icon(Lucide.Settings, PhGear, 'duotone');
export const Shovel = icon(Lucide.Shovel, PhShovel, 'duotone');
export const Skull = icon(Lucide.Skull, PhSkull, 'duotone');
export const Sofa = icon(Lucide.Sofa, PhCouch, 'duotone');
export const Sprout = icon(Lucide.Sprout, PhPlant, 'duotone');
export const Sun = icon(Lucide.Sun, PhSun, 'duotone');
export const Toilet = icon(Lucide.Toilet, PhToilet, 'duotone');
export const Trash2 = icon(Lucide.Trash2, PhTrash);
export const Trophy = icon(Lucide.Trophy, PhTrophy, 'duotone');
export const Tv = icon(Lucide.Tv, PhTelevision, 'duotone');
export const UserMinus = icon(Lucide.UserMinus, PhUserMinus);
export const Users = icon(Lucide.Users, PhUsers, 'duotone');
export const Warehouse = icon(Lucide.Warehouse, PhWarehouse, 'duotone');
export const WashingMachine = icon(Lucide.WashingMachine, PhWashingMachine, 'duotone');
export const X = icon(Lucide.X, PhX);
