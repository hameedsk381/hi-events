import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router";
import { useMutation } from "@tanstack/react-query";
import { orderClientPublic } from "../../../../../../api/order.client.ts";
import { t } from "@lingui/macro";
import { showError } from "../../../../../../utilites/notifications.tsx";
import { LoadingMask } from "../../../../../common/LoadingMask";
import { trackEvent, AnalyticsEvents } from "../../../../../../utilites/analytics.ts";


interface RazorpayPaymentMethodProps {
    enabled: boolean;
    setSubmitHandler: (submitHandler: () => () => Promise<void>) => void;
}

const loadScript = (src: string) => {
    return new Promise((resolve) => {
        const script = document.createElement("script");
        script.src = src;
        script.onload = () => {
            resolve(true);
        };
        script.onerror = () => {
            resolve(false);
        };
        document.body.appendChild(script);
    });
};

export const RazorpayPaymentMethod = ({ enabled, setSubmitHandler }: RazorpayPaymentMethodProps) => {
    const { eventId, orderShortId } = useParams();
    const navigate = useNavigate();
    const [isScriptLoaded, setIsScriptLoaded] = useState(false);

    const createOrderMutation = useMutation({
        mutationFn: () => orderClientPublic.createRazorpayOrder(Number(eventId!), orderShortId!),
    });

    const verifyPaymentMutation = useMutation({
        mutationFn: (payload: any) => orderClientPublic.verifyRazorpayPayment(Number(eventId!), orderShortId!, payload),
    });

    useEffect(() => {
        loadScript("https://checkout.razorpay.com/v1/checkout.js").then((res) => {
            setIsScriptLoaded(!!res);
        });
    }, []);

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
                        name: "Hi.Events",
                        description: "Ticket Purchase",
                        order_id: orderData.order_id,
                        handler: async function (response: any) {
                            try {
                                await verifyPaymentMutation.mutateAsync({
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature
                                });
                                trackEvent(AnalyticsEvents.PURCHASE_COMPLETED_PAID, { value: orderData.amount });
                                navigate(`/checkout/${eventId}/${orderShortId}/summary`);
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
                            // We could prepopulate user details here if we have them from the order
                        },
                        theme: {
                            color: "#3399cc"
                        }
                    };

                    const rzp1 = new (window as any).Razorpay(options);
                    rzp1.open();
                } catch (err: any) {
                    showError(err?.message || t`Failed to initialize payment.`);
                    reject(err);
                }
            });
        });
    }, [enabled, isScriptLoaded, eventId, orderShortId, createOrderMutation, verifyPaymentMutation, navigate, setSubmitHandler]);

    if (!isScriptLoaded) {
        return <LoadingMask />;
    }

    return null; // Razorpay is a modal, no inline UI needed except maybe a message
};
