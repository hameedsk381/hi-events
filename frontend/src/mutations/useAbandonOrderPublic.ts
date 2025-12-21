import {orderClientPublic} from "../api/order.client.ts";
import {IdParam} from "../types.ts";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {GET_ORDER_PUBLIC_QUERY_KEY} from "../queries/useGetOrderPublic.ts";

export const useAbandonOrderPublic = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, orderShortId}: {
            eventId: IdParam,
            orderShortId: IdParam,
        }) => {
            return orderClientPublic.abandonOrder(eventId, orderShortId);
        },
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_ORDER_PUBLIC_QUERY_KEY, variables.eventId, variables.orderShortId]
            });
        }
    });
}
