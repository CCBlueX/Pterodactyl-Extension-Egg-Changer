import { http } from '@pterodactyl/sdk';

export interface EggOption {
    id: number;
    name: string;
    description?: string | null;
    /** Admin: the ports servers get when they switch to this egg. Server: set only when switching moves the server into this range. */
    port_range?: string | null;
}

export interface Options {
    current: EggOption;
    eggs: EggOption[];
    can_reinstall: boolean;
}

export interface Group {
    id: number;
    name: string;
    description: string | null;
    eggs: EggOption[];
}

const server = (identifier: string) => `/api/client/servers/${identifier}/extensions/egg-changer`;
const admin = '/api/admin/extensions/egg-changer';

export const optionsKey = (identifier: string) => ['egg-changer', identifier] as const;
export const groupsKey = ['egg-changer', 'groups'] as const;

export const getOptions = async (identifier: string) => (await http.get<Options>(`${server(identifier)}/options`)).data;

export const changeEgg = async (identifier: string, input: { egg: number; keep_variables: boolean; reinstall: boolean }) =>
    (await http.post<{ current: EggOption; reinstalling: boolean; address: string | null }>(`${server(identifier)}/change`, input)).data;

export const getGroups = async () => (await http.get<{ groups: Group[]; eggs: EggOption[] }>(`${admin}/groups`)).data;

export const saveGroup = async (group: { id?: number; name: string; description: string; eggs: number[]; port_ranges: Record<number, string> }) =>
    group.id
        ? (await http.put<{ group: Group }>(`${admin}/groups/${group.id}`, group)).data
        : (await http.post<{ group: Group }>(`${admin}/groups`, group)).data;

export const deleteGroup = async (id: number) => (await http.delete(`${admin}/groups/${id}`)).data;
