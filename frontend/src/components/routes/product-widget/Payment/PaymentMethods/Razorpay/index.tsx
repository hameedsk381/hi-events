import { useCallback, useEffect, useState } from "react";
import { useLocation, useParams, useNavigate } from "react-router";
import { useMutation } from "@tanstack/react-query";
import { orderClientPublic } from "../../../../../../api/order.client.ts";
import { t } from "@lingui/macro";
import { showError } from "../../../../../../utilites/notifications.tsx";
import { LoadingMask } from "../../../../../common/LoadingMask";
import { Button, Text } from "@mantine/core";
import { trackEvent, AnalyticsEvents } from "../../../../../../utilites/analytics.ts";
import { Event, Order } from "../../../../../../types.ts";


interface RazorpayPaymentMethodProps {
    enabled: boolean;
    setSubmitHandler: (submitHandler: () => () => Promise<void>) => void;
    order: Order;
    event: Event;
}

const loadScript = (src: string) => {
    return new Promise((resolve) => {
        const script = document.createElement("script");
        script.src = src;
        script.async = true;
        script.onload = () => {
            resolve(true);
        };
        script.onerror = () => {
            resolve(false);
        };
        document.body.appendChild(script);
    });
};

export const RazorpayPaymentMethod = ({ enabled, setSubmitHandler, order, event }: RazorpayPaymentMethodProps) => {
    const { eventId, orderShortId } = useParams();
    const navigate = useNavigate();
    const location = useLocation();
    const [isScriptLoaded, setIsScriptLoaded] = useState(false);
    const [isScriptLoading, setIsScriptLoading] = useState(true);
    const [scriptLoadFailed, setScriptLoadFailed] = useState(false);

    const createOrderMutation = useMutation({
        mutationFn: () => orderClientPublic.createRazorpayOrder(Number(eventId!), orderShortId!),
    });

    const verifyPaymentMutation = useMutation({
        mutationFn: (payload: any) => orderClientPublic.verifyRazorpayPayment(Number(eventId!), orderShortId!, payload),
    });

    const loadRazorpayScript = useCallback(() => {
        setIsScriptLoading(true);
        setScriptLoadFailed(false);
        loadScript("https://checkout.razorpay.com/v1/checkout.js").then((res) => {
            setIsScriptLoaded(!!res);
            setScriptLoadFailed(!res);
            setIsScriptLoading(false);
        });
    }, []);

    useEffect(() => {
        loadRazorpayScript();
    }, [loadRazorpayScript]);

    useEffect(() => {
        if (!enabled || !isScriptLoaded) return;

        setSubmitHandler(() => async () => {
            return new Promise(async (resolve, reject) => {
                try {
                    const orderData = await createOrderMutation.mutateAsync();

                    const options = {
                        key: orderData.key_id,
                        amount: orderData.amount,
                        currency: orderData.currency,
                        name: event.title,
                        description: t`Order #${order.short_id}`,
                        order_id: orderData.order_id,
                        handler: async function (response: any) {
                            try {
                                await verifyPaymentMutation.mutateAsync({
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature
                                });
                                trackEvent(AnalyticsEvents.PURCHASE_COMPLETED_PAID, { value: orderData.amount });
                                 navigate(`/checkout/${eventId}/${orderShortId}/summary${location.search}`);
                                resolve();
                            } catch (error) {
                                showError(t`Payment verification failed. Please contact support.`);
                                reject(error);
                            }
                        },
                        modal: {
                            ondismiss: function () {
                                resolve(); // User cancelled, stop loading
                            }
                        },
                        prefill: {
                            name: `${order.first_name} ${order.last_name}`,
                            email: order.email,
                            contact: '' // Phone number is not currently collected in the order object
                        },
                        theme: {
                            color: (event as any).homepage_theme_settings?.accent || "#3399cc"
                        }
                    };

                    const rzp1 = new (window as any).Razorpay(options);
                    rzp1.on('payment.failed', () => {
                        showError(t`Payment failed. Please try again or use another payment method.`);
                        reject(new Error('Razorpay payment failed'));
                    });
                    rzp1.open();
                } catch (err: any) {
                    showError(err?.message || t`Failed to initialize payment.`);
                    reject(err);
                }
            });
        });
    }, [enabled, isScriptLoaded, eventId, orderShortId, createOrderMutation, verifyPaymentMutation, navigate, location.search, setSubmitHandler, order, event]);

    if (isScriptLoading) {
        return <LoadingMask />;
    }

    if (scriptLoadFailed) {
        return (
            <div>
                <Text c="red">{t`Payment checkout could not be loaded.`}</Text>
                <Button mt="sm" variant="light" onClick={loadRazorpayScript}>
                    {t`Retry`}
                </Button>
            </div>
        );
    }

    return null; // Razorpay is a modal, no inline UI needed except maybe a message
};
