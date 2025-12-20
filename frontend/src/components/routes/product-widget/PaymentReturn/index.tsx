import { usePollGetOrderPublic } from "../../../../queries/usePollGetOrderPublic.ts";
import { useNavigate, useParams } from "react-router";
import { useEffect, useRef, useState } from "react";
import classes from './PaymentReturn.module.scss';
import { t } from "@lingui/macro";
import { CheckoutContent } from "../../../layouts/Checkout/CheckoutContent";
import { eventCheckoutPath } from "../../../../utilites/urlHelper.ts";
import { HomepageInfoMessage } from "../../../common/HomepageInfoMessage";
import { isSsr } from "../../../../utilites/helpers.ts";
import { trackEvent, AnalyticsEvents } from "../../../../utilites/analytics.ts";

/**
 * This component is responsible for handling the return from the payment provider.
 * Stripe should send a webhook to the backend to update the order status to 'COMPLETED'
 * However, if this fails, we will poll the order status to check if the payment has been processed.
 * This is a rare occurrence, but we should handle it gracefully.
 * It will also make local development easier in times when the webhook is not configured correctly.
 **/
export const PaymentReturn = () => {
    const [shouldPoll, setShouldPoll] = useState(true);
    const { eventId, orderShortId } = useParams();
    const { data: order } = usePollGetOrderPublic(eventId, orderShortId, shouldPoll, ['event']);
    const navigate = useNavigate();
    const [cannotConfirmPayment, setCannotConfirmPayment] = useState(false);
    const hasTrackedPurchase = useRef(false);

    useEffect(
        () => {
            const timeout = setTimeout(() => {
                setShouldPoll(false);
                setCannotConfirmPayment(true);
            }, 10000); //todo - this should be a env variable

            return () => {
                clearTimeout(timeout);
            };
        },
        []
    );


    useEffect(() => {
        if (isSsr() || !order) {
            return;
        }

        if (order?.status === 'COMPLETED') {
            if (!hasTrackedPurchase.current) {
                hasTrackedPurchase.current = true;
                const totalCents = Math.round((order.total_gross || 0) * 100);
                trackEvent(AnalyticsEvents.PURCHASE_COMPLETED_PAID, { value: totalCents });
            }
            navigate(eventCheckoutPath(eventId, orderShortId, 'summary'));
        }
        if (order?.payment_status === 'PAYMENT_FAILED' || (typeof window !== 'undefined' && window?.location.search.includes('failed'))) {
            navigate(eventCheckoutPath(eventId, orderShortId, 'payment') + '?payment_failed=true');
        }
    }, [order]);

    return (
        <CheckoutContent>
            <div className={classes.container}>
                {!cannotConfirmPayment && (
                    <HomepageInfoMessage
                        status="processing"
                        message={(
                            <>
                                {!shouldPoll && t`We could not process your payment. Please try again or contact support.`}
                                {shouldPoll && t`We're processing your order. Please wait...`}
                            </>
                        )}
                    />
                )}

                {cannotConfirmPayment && (
                    <HomepageInfoMessage
                        status="error"
                        message={t`We were unable to confirm your payment. Please try again or contact support.`}
                    />
                )}
            </div>
        </CheckoutContent>
    );
}

export default PaymentReturn;
