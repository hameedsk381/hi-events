import { t } from "@lingui/macro";
import { HeadingCard } from "../../../../../common/HeadingCard";
import { useGetAccount } from "../../../../../../queries/useGetAccount.ts";
import { LoadingMask } from "../../../../../common/LoadingMask";
import { Grid, Group, Text, Title } from "@mantine/core";
import paymentClasses from "./PaymentSettings.module.scss";
import { IconAlertCircle } from '@tabler/icons-react';
import { Card } from "../../../../../common/Card";
import { formatCurrency } from "../../../../../../utilites/currency.ts";
import { getConfig } from "../../../../../../utilites/config.ts";

interface FeePlanDisplayProps {
    configuration?: {
        name: string;
        application_fees: {
            percentage: number;
            fixed: number;
        };
        is_system_default: boolean;
    };
}

const formatPercentage = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'percent',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value / 100);
};

const FeePlanDisplay = ({ configuration }: FeePlanDisplayProps) => {
    if (!configuration) return null;

    return (
        <div className={paymentClasses.stripeInfo}>
            <Title mb={10} order={3}>{t`Platform Fees`}</Title>

            <Text size="sm" c="dimmed" mb="lg">
                {getConfig("VITE_APP_NAME", "Hi.Events")} charges platform fees to maintain and improve our services.
                These fees are automatically deducted from each transaction.
            </Text>

            <Card variant={'lightGray'}>
                <Title order={4}>{configuration.name}</Title>
                <Grid>
                    {configuration.application_fees.percentage > 0 && (
                        <Grid.Col span={{ base: 12, sm: 6 }}>
                            <Group gap="xs" wrap={'nowrap'}>
                                <Text size="sm">
                                    {t`Transaction Fee:`}{' '}
                                    <Text span fw={600}>
                                        {formatPercentage(configuration.application_fees.percentage)}
                                    </Text>
                                </Text>
                            </Group>
                        </Grid.Col>
                    )}
                    {configuration.application_fees.fixed > 0 && (
                        <Grid.Col span={{ base: 12, sm: 6 }}>
                            <Group gap="xs" wrap={'nowrap'}>
                                <Text size="sm">
                                    {t`Fixed Fee:`}{' '}
                                    <Text span fw={600}>
                                        {formatCurrency(configuration.application_fees.fixed)}
                                    </Text>
                                </Text>
                            </Group>
                        </Grid.Col>
                    )}
                </Grid>
            </Card>

            <Text size="xs" c="dimmed" mt="md">
                <Group gap="xs" align="center" wrap={'nowrap'}>
                    <IconAlertCircle size={14} />
                    <Text
                        span>{t`Fees are subject to change. You will be notified of any changes to your fee structure.`}</Text>
                </Group>
            </Text>
        </div>
    );
};

const PaymentSettingsContent = () => {
    return (
        <div className={paymentClasses.stripeInfo}>
            <Title mb={10} order={3}>{t`Payment Processing`}</Title>
            <Text size="sm" c="dimmed" mb="lg">
                {t`Online payments are processed via Razorpay. To configure your Razorpay credentials, please contact the system administrator.`}
            </Text>
        </div>
    );
};

const PaymentSettings = () => {
    const accountQuery = useGetAccount();

    if (!accountQuery.data) {
        return <LoadingMask />;
    }

    return (
        <>
            <HeadingCard
                title={t`Payment Settings`}
                description={t`Manage your payment processing and fee structure.`}
            />

            <Card>
                <PaymentSettingsContent />
                <FeePlanDisplay configuration={accountQuery.data.configuration} />
            </Card>
        </>
    );
};

export default PaymentSettings;
