import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Button,
    Checkbox,
    Dialog,
    Select,
    Switch,
    TitledGreyBox,
    httpErrorToHuman,
    invalidateServerData,
    toast,
    useServerPermission,
    useServerResources,
    type StartupFormSlotData,
} from '@pterodactyl/sdk';
import { changeEgg, getOptions, optionsKey } from './api';

export default function EggSwitcher({ data }: { data: StartupFormSlotData }) {
    const { identifier, uuid } = data.server.attributes;
    const canChange = useServerPermission('ext.egg-changer.change');
    const queryClient = useQueryClient();
    const state = useServerResources(uuid, (resources) => resources.attributes.current_state).data;
    const options = useQuery({ queryKey: optionsKey(identifier), queryFn: () => getOptions(identifier), enabled: canChange });
    const [egg, setEgg] = useState<number | null>(null);
    const [keepVariables, setKeepVariables] = useState(true);
    const [reinstall, setReinstall] = useState(true);
    const [confirming, setConfirming] = useState(false);

    const change = useMutation({
        mutationFn: () => changeEgg(identifier, { egg: egg!, keep_variables: keepVariables, reinstall: reinstall && !!options.data?.can_reinstall }),
        onSuccess: async (result) => {
            toast.success(
                [`Switched to ${result.current.name}.`, result.address && `The server now runs on ${result.address}.`, result.reinstalling && 'The server is reinstalling.'].filter(Boolean).join(' ')
            );
            setConfirming(false);
            setEgg(null);
            await Promise.all([data.refresh(), invalidateServerData(uuid, ['server', 'startup']), queryClient.invalidateQueries({ queryKey: optionsKey(identifier) })]);
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });

    if (!canChange || !options.data || options.data.eggs.length === 0) {
        return null;
    }

    const stopped = state === 'offline' || state === 'stopped';
    const target = options.data.eggs.find((item) => item.id === egg);

    return (
        <TitledGreyBox title='Egg' className='egc:mb-8'>
            <p className='egc:mb-3 egc:text-sm egc:text-muted-foreground'>
                This server runs <span className='egc:text-foreground'>{options.data.current.name}</span>. Switch to another egg to change the game or server software.
            </p>
            <div className='egc:grid egc:gap-4 egc:md:grid-cols-[minmax(0,1fr)_auto] egc:md:items-end'>
                <Select
                    aria-label='New egg'
                    placeholder='Choose an egg'
                    value={egg}
                    onChange={(value) => setEgg(Number(value))}
                    options={options.data.eggs.map((item) => ({ value: item.id, label: item.name }))}
                />
                <Button disabled={egg === null || !stopped} onClick={() => setConfirming(true)}>
                    Change egg
                </Button>
            </div>
            {target?.description && <p className='egc:mt-2 egc:line-clamp-2 egc:text-xs egc:text-muted-foreground'>{target.description}</p>}
            <div className='egc:mt-4 egc:grid egc:gap-3'>
                <Switch label='Keep variable values' description='Values the new egg still accepts are kept; everything else goes back to its default.' checked={keepVariables} onChange={setKeepVariables} />
                {options.data.can_reinstall && (
                    <label className='egc:flex egc:items-center egc:gap-2 egc:text-sm'>
                        <Checkbox checked={reinstall} onChange={setReinstall} />
                        Reinstall afterwards, so the new egg installs its files
                    </label>
                )}
            </div>
            {!stopped && <p className='egc:mt-3 egc:text-xs egc:text-warning'>Stop the server to change its egg.</p>}
            <Dialog.Confirm
                open={confirming}
                onClose={() => setConfirming(false)}
                title={`Switch to ${target?.name}?`}
                confirm='Change egg'
                pending={change.isPending}
                onConfirmed={() => change.mutate()}
            >
                The startup command and Docker image are reset to the defaults of {target?.name}.
                {target?.port_range && ` The server moves to a free port in ${target.port_range}.`}
                {reinstall && options.data.can_reinstall && ' The install script of the new egg runs afterwards and can overwrite files.'}
            </Dialog.Confirm>
        </TitledGreyBox>
    );
}
