import { h, ref } from 'vue';
import * as Lucide from '@lucide/vue';
import {
    PhArrowElbowDownRight, PhArrowRight, PhCalendarDots, PhCaretDown, PhCaretLeft, PhCaretRight, PhChartBar, PhCheck, PhCheckCircle,
    PhCloudFog, PhCloudRain, PhCompass, PhDotsThree, PhDrop, PhDropHalfBottom, PhFire, PhFolderSimplePlus, PhGear, PhGridFour, PhLeaf,
    PhList, PhListChecks, PhLock, PhPencilSimple, PhPlant, PhPlus, PhRecycle, PhRepeat, PhScissors, PhShovel, PhSignOut, PhSun,
    PhTextAlignLeft, PhTrash, PhTray, PhTrophy, PhUserMinus, PhUsers, PhWarehouse, PhX,
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
export const ArrowRight = icon(Lucide.ArrowRight, PhArrowRight);
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
export const CornerDownRight = icon(Lucide.CornerDownRight, PhArrowElbowDownRight);
export const Droplet = icon(Lucide.Droplet, PhDrop, 'duotone');
export const Droplets = icon(Lucide.Droplets, PhDropHalfBottom, 'duotone');
export const Ellipsis = icon(Lucide.Ellipsis, PhDotsThree);
export const Flame = icon(Lucide.Flame, PhFire, 'duotone');
export const FolderInput = icon(Lucide.FolderInput, PhFolderSimplePlus);
export const Grid2x2 = icon(Lucide.Grid2x2, PhGridFour, 'duotone');
export const Inbox = icon(Lucide.Inbox, PhTray, 'duotone');
export const Leaf = icon(Lucide.Leaf, PhLeaf, 'duotone');
export const ListChecks = icon(Lucide.ListChecks, PhListChecks);
export const Lock = icon(Lucide.Lock, PhLock, 'duotone');
export const LogOut = icon(Lucide.LogOut, PhSignOut);
export const Menu = icon(Lucide.Menu, PhList);
export const Pencil = icon(Lucide.Pencil, PhPencilSimple);
export const Plus = icon(Lucide.Plus, PhPlus);
export const Recycle = icon(Lucide.Recycle, PhRecycle, 'duotone');
export const Repeat = icon(Lucide.Repeat, PhRepeat);
export const Scissors = icon(Lucide.Scissors, PhScissors, 'duotone');
export const Settings = icon(Lucide.Settings, PhGear, 'duotone');
export const Shovel = icon(Lucide.Shovel, PhShovel, 'duotone');
export const Sprout = icon(Lucide.Sprout, PhPlant, 'duotone');
export const Sun = icon(Lucide.Sun, PhSun, 'duotone');
export const Trash2 = icon(Lucide.Trash2, PhTrash);
export const Trophy = icon(Lucide.Trophy, PhTrophy, 'duotone');
export const UserMinus = icon(Lucide.UserMinus, PhUserMinus);
export const Users = icon(Lucide.Users, PhUsers, 'duotone');
export const Warehouse = icon(Lucide.Warehouse, PhWarehouse, 'duotone');
export const X = icon(Lucide.X, PhX);
