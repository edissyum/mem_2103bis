import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { tap, catchError, debounceTime } from 'rxjs/operators';
import { HttpClient } from '@angular/common/http';
import { of } from 'rxjs';
import { FormBuilder, FormGroup } from '@angular/forms';
import { NotificationService } from '@service/notification/notification.service';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-life-cyle',
    templateUrl: './life-cycle.component.html',
})

export class LifeCycleComponent implements OnInit {
    documentFinalAction: FormGroup;
    finalActionValues: any[] = ['restrictAccess', 'transfer', 'copy', 'delete'];

    hasError: boolean = false;
    loading: boolean = false;
    isSae: boolean = false;
    archivalError: string = '';
    result: string  = '';
    urlSAEService: string = '';

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public functions: FunctionsService,
        private _formBuilder: FormBuilder,
        private notify: NotificationService,
    ) {
        this.documentFinalAction = this._formBuilder.group({
            bindingDocumentFinalAction: [''],
            nonBindingDocumentFinalAction: ['']
        });
    }

    async ngOnInit(): Promise<void> {
        await this.getFinalAction();
        await this.getSaeConfiguration();
    }

    getFinalAction() {
        return new Promise((resolve) => {
            this.http.get('../rest/parameters').pipe(
                tap((data: any) => {
                    const bindDocumentFinalAction = data.parameters.filter((item: { id: any }) => item.id === 'bindingDocumentFinalAction')[0].param_value_string;
                    const nonBindDocumentFinalAction = data.parameters.filter((item: { id: any }) => item.id === 'nonBindingDocumentFinalAction')[0].param_value_string;
                    this.documentFinalAction.controls['bindingDocumentFinalAction'].setValue(bindDocumentFinalAction);
                    this.documentFinalAction.controls['nonBindingDocumentFinalAction'].setValue(nonBindDocumentFinalAction);

                    setTimeout(() => {
                        this.documentFinalAction.controls['bindingDocumentFinalAction'].valueChanges.pipe(
                            debounceTime(100),
                            tap(() => this.saveParameter('bindingDocumentFinalAction'))
                        ).subscribe();

                        this.documentFinalAction.controls['nonBindingDocumentFinalAction'].valueChanges.pipe(
                            debounceTime(100),
                            tap(() => this.saveParameter('nonBindingDocumentFinalAction'))
                        ).subscribe();
                    });
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    saveParameter(id: string) {
        let param =  {};
        param = {
            param_value_string : this.documentFinalAction.controls[id].value
        };
        this.http.put('../rest/parameters/' + id, param)
            .subscribe(() => {
                this.notify.success(this.translate.instant('lang.parameterUpdated'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    getSaeConfiguration() {
        return new Promise((resolve) => {
            this.http.get('../rest/seda/configuration').pipe(
                tap((data: any) => {
                    const exportSedaSae: string = data.exportSeda.sae;
                    this.isSae = exportSedaSae.toLocaleLowerCase() === 'maarchrm' ? true : false;
                    this.urlSAEService = !this.functions.empty(data.exportSeda.urlSAEService) ? data.exportSeda.urlSAEService : '';
                    resolve(this.isSae);
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    checkInterconnection() {
        this.loading = true;
        this.result = '';
        this.http.get('../rest/archival/retentionRules').pipe(
            tap(() => {
                this.loading = false;
                this.hasError = false;
                this.result = this.translate.instant('lang.interconnectionSuccess');
            }),
            catchError((err: any) => {
                this.hasError = true;
                this.loading = false;
                this.archivalError = err.error.errors;
                const index: number = this.archivalError.indexOf(':');
                const getError: string = this.archivalError.slice(index + 1, this.archivalError.length).replace(/^[\s]/, '');
                this.archivalError = !this.functions.empty(getError) ? `(${getError})` : '';
                this.result = this.translate.instant('lang.interconnectionFailed') + ` ${this.urlSAEService} ` + this.archivalError;
                return of(false);
            })
        ).subscribe();
    }

}
