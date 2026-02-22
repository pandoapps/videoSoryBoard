import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ApiKeysStatus, PageProps } from '@/types';
import { FormEventHandler } from 'react';

interface Props extends PageProps {
    apiKeys: ApiKeysStatus;
}

export default function Settings({ apiKeys }: Props) {
    const { flash } = usePage<PageProps>().props;

    const { data, setData, post, processing } = useForm({
        anthropic: '',
        nano_banana: '',
        kling_access_key: '',
        kling_secret_key: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.settings.update'));
    };

    const providers = [
        {
            key: 'anthropic' as const,
            label: 'Anthropic (Claude)',
            description: 'Used for AI-powered script writing chat',
            helpUrl: 'https://console.anthropic.com/settings/keys',
            helpText: 'Create an account at console.anthropic.com, go to Settings → API Keys, and generate a new key.',
        },
        {
            key: 'nano_banana' as const,
            label: 'Nano Banana',
            description: 'Used for character and storyboard image generation',
            helpUrl: 'https://nanobananaapi.ai/',
            helpText: 'Create an account at nanobananaapi.ai, go to your dashboard and copy your API token.',
        },
    ];

    const allConfigured = apiKeys.anthropic.configured && apiKeys.nano_banana.configured && apiKeys.kling.configured;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    API Settings
                </h2>
            }
        >
            <Head title="API Settings" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="mb-4 rounded-md bg-green-50 p-4">
                            <p className="text-sm text-green-700">{flash.success}</p>
                        </div>
                    )}
                    {flash?.warning && (
                        <div className="mb-4 rounded-md bg-yellow-50 p-4">
                            <p className="text-sm text-yellow-700">{flash.warning}</p>
                        </div>
                    )}

                    {!allConfigured && (
                        <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5">
                            <h3 className="text-sm font-semibold text-blue-800 mb-2">Setup Required</h3>
                            <p className="text-sm text-blue-700 mb-3">
                                To use the video generation pipeline you need to configure API keys for each service below. Each user has their own keys.
                            </p>
                            <ol className="text-sm text-blue-700 list-decimal list-inside space-y-1">
                                <li>
                                    <strong>Anthropic</strong> — Get your key at{' '}
                                    <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer" className="underline font-medium">
                                        console.anthropic.com
                                    </a>
                                    {' '}(create account → Settings → API Keys)
                                </li>
                                <li>
                                    <strong>Nano Banana</strong> — Get your token at{' '}
                                    <a href="https://nanobananaapi.ai/" target="_blank" rel="noopener noreferrer" className="underline font-medium">
                                        nanobananaapi.ai
                                    </a>
                                    {' '}(create account → Dashboard → API Token)
                                </li>
                                <li>
                                    <strong>Kling AI</strong> — Get your keys at{' '}
                                    <a href="https://app.klingai.com/global/dev/api-key" target="_blank" rel="noopener noreferrer" className="underline font-medium">
                                        app.klingai.com
                                    </a>
                                    {' '}(create account → Developer → API Key → copy Access Key and Secret Key)
                                </li>
                            </ol>
                        </div>
                    )}

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-6">
                                Configure API Keys
                            </h3>
                            <form onSubmit={submit} className="space-y-6">
                                {providers.map((provider) => (
                                    <div key={provider.key}>
                                        <div className="flex items-center justify-between mb-1">
                                            <label
                                                htmlFor={provider.key}
                                                className="block text-sm font-medium text-gray-700"
                                            >
                                                {provider.label}
                                            </label>
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    apiKeys[provider.key].configured
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                }`}
                                            >
                                                {apiKeys[provider.key].configured
                                                    ? 'Configured'
                                                    : 'Not configured'}
                                            </span>
                                        </div>
                                        <p className="text-sm text-gray-500 mb-2">
                                            {provider.description}
                                            {!apiKeys[provider.key].configured && (
                                                <> — <a href={provider.helpUrl} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-500 underline">Get your key</a></>
                                            )}
                                        </p>
                                        <input
                                            id={provider.key}
                                            type="password"
                                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            placeholder={
                                                apiKeys[provider.key].configured
                                                    ? 'Key is set (enter new value to update)'
                                                    : 'Enter API key'
                                            }
                                            value={data[provider.key]}
                                            onChange={(e) =>
                                                setData(provider.key, e.target.value)
                                            }
                                        />
                                    </div>
                                ))}

                                {/* Kling AI — two separate fields */}
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="block text-sm font-medium text-gray-700">
                                            Kling AI
                                        </label>
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                apiKeys.kling.configured
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}
                                        >
                                            {apiKeys.kling.configured
                                                ? 'Configured'
                                                : 'Not configured'}
                                        </span>
                                    </div>
                                    <p className="text-sm text-gray-500 mb-3">
                                        Used for video generation
                                    </p>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                htmlFor="kling_access_key"
                                                className="block text-xs font-medium text-gray-500 mb-1"
                                            >
                                                Access Key
                                            </label>
                                            <input
                                                id="kling_access_key"
                                                type="password"
                                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder={
                                                    apiKeys.kling.configured
                                                        ? 'Key is set (enter new value to update)'
                                                        : 'Enter Access Key'
                                                }
                                                value={data.kling_access_key}
                                                onChange={(e) =>
                                                    setData('kling_access_key', e.target.value)
                                                }
                                            />
                                        </div>
                                        <div>
                                            <label
                                                htmlFor="kling_secret_key"
                                                className="block text-xs font-medium text-gray-500 mb-1"
                                            >
                                                Secret Key
                                            </label>
                                            <input
                                                id="kling_secret_key"
                                                type="password"
                                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder={
                                                    apiKeys.kling.configured
                                                        ? 'Key is set (enter new value to update)'
                                                        : 'Enter Secret Key'
                                                }
                                                value={data.kling_secret_key}
                                                onChange={(e) =>
                                                    setData('kling_secret_key', e.target.value)
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                    >
                                        {processing ? 'Saving...' : 'Save API Keys'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
