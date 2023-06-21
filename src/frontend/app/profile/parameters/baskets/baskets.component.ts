import { Component, Input, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NotificationService } from '@service/notification/notification.service';
import { TranslateService } from '@ngx-translate/core';
import { FunctionsService } from '@service/functions.service';
import { HeaderService } from '@service/header.service';
import { SelectionModel } from '@angular/cdk/collections';

@Component({
    selector: 'app-my-baskets',
    templateUrl: './baskets.component.html',
    styleUrls: ['./baskets.component.scss'],
})

export class MyBasketsComponent implements OnInit {

    @Input() userBaskets: any[];
    @Input() redirectedBaskets: any[];
    @Input() assignedBaskets: any[];

    selectionBaskets = new SelectionModel<Element>(true, []);

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functionsService: FunctionsService,
        public headerService: HeaderService,
    ) {}

    // EDISSYUM - NCH01 Fix pour ne pas que la case "Tout selectionner" soit cochée si elle ne doit pas l'être
    allSelected() {
        return this.selectionBaskets.selected.length == this.userBaskets.length;
    }
    // END EDISSYUM - NCH01

    ngOnInit(): void {}

    masterToggleBaskets(event: any) {
        if (event.checked) {
            this.userBaskets.forEach((basket: any) => {
                if (!basket.userToDisplay) {
                    this.selectionBaskets.select(basket);
                }
            });
        } else {
            this.selectionBaskets.clear();
        }
    }

    addBasketRedirection(newUser: any) {
        const basketsRedirect: any[] = [];

        this.selectionBaskets.selected.forEach((elem: any) => {
            basketsRedirect.push(
                {
                    actual_user_id: newUser.serialId,
                    basket_id: elem.basket_id,
                    group_id: elem.groupSerialId,
                    originalOwner: null
                }
            );
        });

        const r = confirm(this.translate.instant('lang.confirmAction') + ' ' + this.translate.instant('lang.redirectBasket'));

        if (r) {
            this.http.post('../rest/users/' + this.headerService.user.id + '/redirectedBaskets', basketsRedirect)
                .subscribe((data: any) => {
                    this.userBaskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                    this.redirectedBaskets = data['redirectedBaskets'];
                    this.selectionBaskets.clear();
                    this.notify.success(this.translate.instant('lang.basketUpdated'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    reassignBasketRedirection(newUser: any, basket: any, i: number) {
        const r = confirm(this.translate.instant('lang.confirmAction') + ' ' + this.translate.instant('lang.redirectBasket'));

        if (r) {
            this.http.post('../rest/users/' + this.headerService.user.id + '/redirectedBaskets', [
                {
                    'actual_user_id': newUser.serialId,
                    'basket_id': basket.basket_id,
                    'group_id': basket.group_id,
                    'originalOwner': basket.owner_user_id,
                }
            ]).subscribe((data: any) => {
                this.userBaskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                this.assignedBaskets.splice(i, 1);
                this.notify.success(this.translate.instant('lang.basketUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
        }
    }

    delBasketAssignRedirection(basket: any, i: number) {
        const r = confirm(this.translate.instant('lang.confirmAction'));

        if (r) {
            this.http.delete('../rest/users/' + this.headerService.user.id + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)
                .subscribe((data: any) => {
                    this.headerService.user.baskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                    this.assignedBaskets.splice(i, 1);
                    this.notify.success(this.translate.instant('lang.basketUpdated'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    delBasketRedirection(basket: any, i: number) {
        const r = confirm(this.translate.instant('lang.confirmAction'));

        if (r) {
            this.http.delete('../rest/users/' + this.headerService.user.id + '/redirectedBaskets?redirectedBasketIds[]=' + basket.id)
                .subscribe((data: any) => {
                    this.userBaskets = data['baskets'].filter((basketItem: any) => !basketItem.basketSearch);
                    this.redirectedBaskets.splice(i, 1);
                    this.notify.success(this.translate.instant('lang.basketUpdated'));
                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    showActions(basket: any) {
        $('#' + basket.basket_id + '_' + basket.group_id).show();
    }

    hideActions(basket: any) {
        $('#' + basket.basket_id + '_' + basket.group_id).hide();
    }
}
