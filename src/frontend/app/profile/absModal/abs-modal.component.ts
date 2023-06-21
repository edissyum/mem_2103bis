import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, exhaustMap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { HeaderService } from '@service/header.service';
import { AuthService } from '@service/auth.service';

@Component({
    templateUrl: 'abs-modal.component.html',
    styleUrls: ['abs-modal.component.scss'],
})
export class AbsModalComponent implements OnInit {

    loading: boolean = false;

    userId: number = 0;
    baskets: any[] = [];

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public headerService: HeaderService,
        public dialogRef: MatDialogRef<AbsModalComponent>,
        private authService: AuthService,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) { }

    ngOnInit(): void {
        this.getBasketInfo();
    }

    getBasketInfo() {
        let objBasket = {};
        this.data.user.baskets.filter((basket: any) => !basket.basketSearch).forEach((basket: any) => {
            objBasket = { ...basket };

            const redirBasket = this.data.user.redirectedBaskets.find((redBask: any) => redBask.basket_id === basket.basket_id && redBask.group_id === basket.groupSerialId);
            if (redirBasket !== undefined) {
                objBasket['actual_user_id'] = redirBasket.actual_user_id;
            }
            this.baskets.push(objBasket);
        });
    }

    async onSubmit() {
        this.loading = true;
        const res = await this.redirectBaskets();
        if (res) {
            await this.activateAbsence();
        }
        this.loading = false;
        this.dialogRef.close();
    }

    isRedirectedBasket(basket: any) {
        return basket.userToDisplay !== null;
    }

    addBasketRedirection(newUser: any) {
        this.baskets.forEach((basket: any, index: number) => {
            if (basket.selected) {
                this.baskets[index] = {
                    ...basket,
                    actual_user_id: newUser.serialId,
                    userToDisplay: newUser.idToDisplay,
                    selected: false
                };
            }
        });
    }

    delBasketRedirection(basket: any) {
        basket.actual_user_id = null;
        basket.userToDisplay = null;
    }

    redirectBaskets() {
        return new Promise(async (resolve, reject) => {
            const res = await this.clearRedirections();
            if (res) {
                const basketsRedirect: any[] = [];

                this.baskets.filter((item: any) => item.userToDisplay !== null).forEach((elem: any) => {
                    if (!this.isInitialRedirection(elem)) {
                        basketsRedirect.push(
                            {
                                actual_user_id: elem.actual_user_id,
                                basket_id: elem.basket_id,
                                group_id: elem.groupSerialId,
                                originalOwner: null
                            }
                        );
                    }
                });
                if (basketsRedirect.length > 0) {
                    this.http.post('../rest/users/' + this.data.user.id + '/redirectedBaskets', basketsRedirect).pipe(
                        tap((data: any) => {
                            resolve(true);
                        }),
                        catchError((err: any) => {
                            this.notify.handleErrors(err);
                            resolve(false);
                            return of(false);
                        })
                    ).subscribe();
                } else {
                    resolve(true);
                }
            } else {
                resolve(false);
            }
        });
    }

    isInitialRedirection(basket: any) {
        return this.data.user.redirectedBaskets.find((redBasket: any) => basket.basket_id === redBasket.basket_id && basket.groupSerialId === redBasket.group_id && basket.actual_user_id === redBasket.actual_user_id);
    }

    clearRedirections() {
        return new Promise(async (resolve, reject) => {
            const redirectedBasketIds: number[] = [];
            this.data.user.redirectedBaskets.forEach((redBasket: any) => {
                if (this.baskets.find((basket: any) => basket.basket_id === redBasket.basket_id && basket.groupSerialId === redBasket.group_id && basket.actual_user_id !== redBasket.actual_user_id) !== undefined) {
                    redirectedBasketIds.push(redBasket.id);
                }
            });
            if (redirectedBasketIds.length > 0) {
                const res = await this.delBasketAssignRedirection(redirectedBasketIds);
                resolve(res);
            } else {
                resolve(true);
            }
        });
    }

    delBasketAssignRedirection(redirectedBasketIds: number[]) {
        const queryParam = '?redirectedBasketIds[]=' + redirectedBasketIds.join('&redirectedBasketIds[]=');

        return new Promise((resolve, reject) => {
            this.http.delete('../rest/users/' + this.data.user.id + '/redirectedBaskets' + queryParam).pipe(
                tap((data: any) => {
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    basketSelected() {
        return this.baskets.filter((item: any) => item.selected).length > 0;
    }

    activateAbsence() {
        return new Promise((resolve, reject) => {
            this.http.put('../rest/users/' + this.data.user.id + '/status', { 'status': 'ABS' }).pipe(
                tap((data: any) => {
                    this.authService.logout();
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    resolve(false);
                    return of(false);
                })
            ).subscribe();
        });
    }

    toggleAll() {
        if (this.allSelected()) {
            this.baskets.forEach(element => {
                element.selected = false;
            });
        } else {
            this.baskets.forEach(element => {
                if (!this.isRedirectedBasket(element)) {
                    element.selected = true;
                }
            });
        }
    }

    allSelected() {
        return this.baskets.filter((item: any) => item.selected).length === this.baskets.filter((item: any) => !this.isRedirectedBasket(item)).length;
    }

    oneOrMoreSelected() {
        return this.baskets.filter((item: any) => item.selected).length > 0 && !this.allSelected();
    }
}
