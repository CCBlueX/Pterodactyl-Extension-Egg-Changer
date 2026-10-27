import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Button,
    Dialog,
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
    Input,
    Label,
    NamedIcon,
    PageContentBlock,
    Select,
    Spinner,
    TextArea,
    TitledGreyBox,
    httpErrorToHuman,
    toast,
} from '@pterodactyl/sdk';
import { deleteGroup, getGroups, groupsKey, saveGroup, type EggOption, type Group } from '../api';

interface Draft {
    id?: number;
    name: string;
    description: string;
    eggs: number[];
    port_ranges: Record<number, string>;
}

export default function GroupsScreen() {
    const queryClient = useQueryClient();
    const groups = useQuery({ queryKey: groupsKey, queryFn: getGroups });
    const [draft, setDraft] = useState<Draft | null>(null);
    const [removing, setRemoving] = useState<Group | null>(null);
    const refresh = () => queryClient.invalidateQueries({ queryKey: groupsKey });
    const ranges: Record<number, string> = Object.fromEntries((groups.data?.eggs ?? []).map((egg) => [egg.id, egg.port_range ?? '']));

    const save = useMutation({
        mutationFn: () => saveGroup(draft!),
        onSuccess: () => {
            toast.success('Group saved.');
            setDraft(null);
            refresh();
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });
    const removal = useMutation({
        mutationFn: () => deleteGroup(removing!.id),
        onSuccess: () => {
            setRemoving(null);
            refresh();
        },
        onError: (error) => toast.error(httpErrorToHuman(error)),
    });

    return (
        <PageContentBlock title='Egg Changer'>
            <header className='egc:mb-6 egc:flex egc:flex-col egc:gap-3 egc:sm:flex-row egc:sm:items-center egc:sm:justify-between'>
                <div className='egc:min-w-0'>
                    <h1 className='egc:font-header egc:text-2xl egc:font-semibold egc:text-foreground'>Egg Changer</h1>
                    <p className='egc:mt-1 egc:text-sm egc:leading-relaxed egc:text-foreground/70'>
                        Users can switch a server to any egg that shares a group with its current egg, from the server's Startup page.
                    </p>
                </div>
                <Button onClick={() => setDraft({ name: '', description: '', eggs: [], port_ranges: ranges })}>New group</Button>
            </header>

            {groups.isLoading ? (
                <Spinner centered size={Spinner.Size.LARGE} />
            ) : groups.isError ? (
                <p className='egc:text-sm egc:text-destructive'>{httpErrorToHuman(groups.error)}</p>
            ) : groups.data!.groups.length === 0 ? (
                <Empty className='egc:border'>
                    <EmptyHeader>
                        <EmptyMedia variant='icon'>
                            <NamedIcon name='egg' />
                        </EmptyMedia>
                        <EmptyTitle>No groups yet</EmptyTitle>
                        <EmptyDescription>Create a group such as "Minecraft Java" with Paper, Fabric and Forge to let users switch between them.</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <div className='egc:grid egc:gap-4 egc:md:grid-cols-2'>
                    {groups.data!.groups.map((group) => (
                        <TitledGreyBox key={group.id} title={group.name}>
                            {group.description && <p className='egc:mb-3 egc:text-sm egc:text-muted-foreground'>{group.description}</p>}
                            <div className='egc:flex egc:flex-wrap egc:gap-1.5'>
                                {group.eggs.map((egg) => (
                                    <span key={egg.id} className='egc:rounded-full egc:bg-muted egc:px-2 egc:py-0.5 egc:text-xs egc:text-foreground'>
                                        {egg.name}
                                        {egg.port_range && <span className='egc:text-muted-foreground'> · {egg.port_range}</span>}
                                    </span>
                                ))}
                            </div>
                            <div className='egc:mt-4 egc:flex egc:justify-end egc:gap-2'>
                                <Button.Text size='small' onClick={() => setDraft({ id: group.id, name: group.name, description: group.description ?? '', eggs: group.eggs.map((egg) => egg.id), port_ranges: ranges })}>
                                    Edit
                                </Button.Text>
                                <Button.Danger size='small' onClick={() => setRemoving(group)}>
                                    Delete
                                </Button.Danger>
                            </div>
                        </TitledGreyBox>
                    ))}
                </div>
            )}

            <GroupDialog draft={draft} eggs={groups.data?.eggs ?? []} pending={save.isPending} onChange={setDraft} onSave={() => save.mutate()} />

            <Dialog.Confirm
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={`Delete ${removing?.name}?`}
                confirm='Delete'
                pending={removal.isPending}
                onConfirmed={() => removal.mutate()}
            >
                Servers keep their current egg. Users can no longer switch between the eggs of this group.
            </Dialog.Confirm>
        </PageContentBlock>
    );
}

interface DialogProps {
    draft: Draft | null;
    eggs: EggOption[];
    pending: boolean;
    onChange: (draft: Draft | null) => void;
    onSave: () => void;
}

function GroupDialog({ draft, eggs, pending, onChange, onSave }: DialogProps) {
    const valid = draft !== null && draft.name.trim() !== '' && draft.eggs.length >= 2;

    return (
        <Dialog open={draft !== null} onClose={() => onChange(null)} title={draft?.id ? 'Edit group' : 'New group'} preventExternalClose={pending}>
            {draft && (
                <div className='egc:grid egc:gap-4'>
                    <div>
                        <Label htmlFor='egc-name'>Name</Label>
                        <Input id='egc-name' autoFocus value={draft.name} onChange={(event) => onChange({ ...draft, name: event.currentTarget.value })} />
                    </div>
                    <div>
                        <Label htmlFor='egc-description'>Description</Label>
                        <TextArea id='egc-description' rows={2} value={draft.description} onChange={(event) => onChange({ ...draft, description: event.currentTarget.value })} />
                    </div>
                    <div>
                        <Label htmlFor='egc-eggs'>Eggs</Label>
                        <Select
                            id='egc-eggs'
                            multiple
                            placeholder='Pick at least two eggs'
                            value={draft.eggs}
                            onChange={(value) => onChange({ ...draft, eggs: value.map(Number) })}
                            options={eggs.map((egg) => ({ value: egg.id, label: egg.name }))}
                        />
                    </div>
                    {draft.eggs.length > 0 && (
                        <div className='egc:grid egc:gap-2'>
                            <Label>Port ranges</Label>
                            {draft.eggs.map((id) => {
                                const name = eggs.find((egg) => egg.id === id)?.name ?? `Egg ${id}`;

                                return (
                                    <div key={id} className='egc:grid egc:items-center egc:gap-2 egc:sm:grid-cols-[minmax(0,1fr)_12rem]'>
                                        <span className='egc:truncate egc:text-sm egc:text-foreground'>{name}</span>
                                        <Input
                                            aria-label={`Port range for ${name}`}
                                            placeholder='Any port'
                                            maxLength={191}
                                            value={draft.port_ranges[id] ?? ''}
                                            onChange={(event) => onChange({ ...draft, port_ranges: { ...draft.port_ranges, [id]: event.currentTarget.value } })}
                                        />
                                    </div>
                                );
                            })}
                            <p className='egc:text-xs egc:text-muted-foreground'>
                                A server switching to an egg with a range, such as 5520-5600, moves to a free port in it unless its port already fits. Ranges belong to the egg, in every group.
                            </p>
                        </div>
                    )}
                </div>
            )}
            <Dialog.Footer>
                <Button.Text className='egc:w-full egc:sm:w-auto' disabled={pending} onClick={() => onChange(null)}>
                    Cancel
                </Button.Text>
                <Button className='egc:w-full egc:sm:w-auto' disabled={!valid} isLoading={pending} onClick={onSave}>
                    Save
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
}
